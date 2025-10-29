<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('cliente');
require_once __DIR__.'/../../app/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /cliente/dashboard.php');
    exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
    $_SESSION['flash_error'] = 'Token CSRF inválido.';
    header('Location: /cliente/dashboard.php');
    exit;
}

$facturaId = (int)($_POST['factura_id'] ?? 0);
$metodo = strtolower(trim((string)($_POST['metodo'] ?? '')));
$validos = ['paypal','bancolombia','pse','tarjeta'];
if ($facturaId <= 0 || !in_array($metodo, $validos, true) || !($pdo instanceof PDO)) {
    $_SESSION['flash_error'] = 'Solicitud de pago inválida.';
    header('Location: /cliente/dashboard.php');
    exit;
}

try {
    // Asegurar tabla pagos fuera de transacción (DDL hace commit implícito en MySQL)
    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS pagos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            factura_id INT NOT NULL,
            metodo ENUM("paypal","bancolombia","pse","tarjeta") NOT NULL,
            estado ENUM("iniciado","pendiente","aprobado","rechazado") DEFAULT "iniciado",
            referencia VARCHAR(64) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (factura_id),
            CONSTRAINT fk_pagos_factura FOREIGN KEY (factura_id) REFERENCES facturas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } catch (Throwable $e) {
        // si falla la creación, continuamos: la tabla puede existir
    }

    $pdo->beginTransaction();

    // Obtener cliente del usuario
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $st = $pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1');
    $st->execute([$userId]);
    $clienteId = (int)($st->fetchColumn() ?: 0);
    if ($clienteId <= 0) { throw new Exception('Cliente no encontrado.'); }

    // Validar factura del cliente y que esté pendiente
    $q = $pdo->prepare('SELECT f.id AS factura_id, f.estado_pago, f.fecha_factura, p.id AS pedido_id, p.cliente_id FROM facturas f INNER JOIN pedidos p ON p.id=f.pedido_id WHERE f.id=? LIMIT 1');
    $q->execute([$facturaId]);
    $row = $q->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row || (int)($row['cliente_id'] ?? 0) !== $clienteId) { throw new Exception('Factura no pertenece a tu cuenta.'); }
    if (strtolower((string)($row['estado_pago'] ?? 'pendiente')) !== 'pendiente') { throw new Exception('La factura no está pendiente.'); }

    // Generar referencia
    $salt = 'RTH-PAY-2024';
    $base = $facturaId.'|'.$metodo.'|'.substr((string)($row['fecha_factura'] ?? ''),0,10).'|'.$salt;
    $ref = strtoupper('REF-'.substr(hash('sha256', $base), 0, 10));

    // Insertar registro de pago como aprobado
    $ins = $pdo->prepare('INSERT INTO pagos (factura_id, metodo, estado, referencia) VALUES (?,?,"aprobado",?)');
    $ins->execute([$facturaId, $metodo, $ref]);

    // Marcar la factura como pagada
    $upPago = $pdo->prepare('UPDATE facturas SET estado_pago="pagado" WHERE id=?');
    $upPago->execute([$facturaId]);

    // Si existe columna metodo_pago en facturas, actualizar
    try {
        $hasMetodo = (bool)$pdo->query("SHOW COLUMNS FROM facturas LIKE 'metodo_pago'")->rowCount();
        if ($hasMetodo) {
            $up = $pdo->prepare('UPDATE facturas SET metodo_pago=? WHERE id=?');
            $up->execute([$metodo, $facturaId]);
        }
        // Si existe columna fecha_pago, actualizarla a NOW()
        $hasFechaPago = (bool)$pdo->query("SHOW COLUMNS FROM facturas LIKE 'fecha_pago'")->rowCount();
        if ($hasFechaPago) {
            $upFecha = $pdo->prepare('UPDATE facturas SET fecha_pago=NOW() WHERE id=?');
            $upFecha->execute([$facturaId]);
        }
    } catch (Throwable $e) {
        // Silencioso
    }

    $pdo->commit();
    $_SESSION['flash_success'] = 'Pago confirmado con '.ucfirst($metodo).'. Referencia: '.$ref;
    header('Location: /cliente/factura.php?factura_id='.$facturaId);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = $e->getMessage() ?: 'No se pudo iniciar el pago.';
    header('Location: /cliente/dashboard.php');
    exit;
}