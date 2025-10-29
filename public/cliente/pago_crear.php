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
    $_SESSION['flash_error'] = 'Token CSRF inválido, por favor intenta de nuevo.';
    header('Location: /cliente/dashboard.php');
    exit;
}

$pedidoId = (int)($_POST['pedido_id'] ?? 0);
if ($pedidoId <= 0 || !($pdo instanceof PDO)) {
    $_SESSION['flash_error'] = 'Solicitud inválida.';
    header('Location: /cliente/dashboard.php');
    exit;
}

$returnToPago = (isset($_POST['return']) && $_POST['return'] === 'pago');

try {
    $pdo->beginTransaction();

    // Obtener cliente_id del usuario actual
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $st = $pdo->prepare('SELECT id, correo FROM clientes WHERE usuario_id = ? LIMIT 1');
    $st->execute([$userId]);
    $cliente = $st->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$cliente) {
        // Intentar enlazar por correo de usuarios si no existe aún el cliente
        $us = $pdo->prepare('SELECT correo_electronico FROM usuarios WHERE id = ? LIMIT 1');
        $us->execute([$userId]);
        $userEmail = (string)($us->fetchColumn() ?: '');
        if ($userEmail !== '') {
            $sc = $pdo->prepare('SELECT id, usuario_id FROM clientes WHERE correo = ? LIMIT 1');
            $sc->execute([$userEmail]);
            $tmp = $sc->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($tmp) {
                if (empty($tmp['usuario_id'])) {
                    $upd = $pdo->prepare('UPDATE clientes SET usuario_id = ? WHERE id = ?');
                    $upd->execute([$userId, (int)$tmp['id']]);
                }
                $cliente = ['id' => (int)$tmp['id'], 'correo' => $userEmail];
            } else {
                $ins = $pdo->prepare('INSERT INTO clientes (nombre, apellido, correo, telefono, direccion, fecha_registro, usuario_id) VALUES (?,?,?,?,?, NOW(), ?)');
                $ins->execute(['', '', $userEmail, '', '', $userId]);
                $cliente = ['id' => (int)$pdo->lastInsertId(), 'correo' => $userEmail];
            }
        }
    }

    $clienteId = (int)($cliente['id'] ?? 0);

    // Validar que el pedido pertenece al cliente
    $sp = $pdo->prepare('SELECT p.id, p.cliente_id, p.plan_id, p.total, pl.precio FROM pedidos p LEFT JOIN planes pl ON pl.id = p.plan_id WHERE p.id = ? LIMIT 1');
    $sp->execute([$pedidoId]);
    $pedido = $sp->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$pedido || (int)$pedido['cliente_id'] !== $clienteId) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'No tienes permiso para generar factura de este pedido.';
        header('Location: /cliente/dashboard.php');
        exit;
    }

    // Determinar monto
    $monto = $pedido['total'];
    if ($monto === null || $monto === '' || (float)$monto <= 0) {
        $monto = $pedido['precio'] ?? 0;
    }
    $monto = number_format((float)$monto, 2, '.', '');

    // Ver si ya existe factura
    $sf = $pdo->prepare('SELECT id, fecha_factura, estado_pago FROM facturas WHERE pedido_id = ? LIMIT 1');
    $sf->execute([$pedidoId]);
    $fact = $sf->fetch(PDO::FETCH_ASSOC) ?: null;

    // Helper de código
    $genCode = function($fid, $fecha) {
        if (!$fid || !$fecha) return null;
        $salt = 'RTH-SALT-2024';
        $base = $fid.'|'.substr((string)$fecha, 0, 10).'|'.$salt;
        return strtoupper(substr(hash('sha256', $base), 0, 8));
    };

    if ($fact) {
        $codigo = $genCode((int)$fact['id'], $fact['fecha_factura']);
        $pdo->commit();
        $_SESSION['flash_success'] = 'Factura existente. Código: '.$codigo.' | Monto: $'.$monto;
        // Redirigir según destino solicitado
        if (!empty($returnToPago)) {
            header('Location: /cliente/pago.php?factura_id='.(int)$fact['id']);
        } else {
            header('Location: /cliente/factura.php?factura_id='.(int)$fact['id']);
        }
        exit;
    }

    // Crear factura (compatibilidad con distintos esquemas de tabla)
    // Detectar columnas disponibles en la tabla 'facturas'
    $hasPlanId = false; $hasMonto = false; $hasMontoTotal = false;
    try {
        $hasPlanId = (bool)$pdo->query("SHOW COLUMNS FROM facturas LIKE 'plan_id'")->rowCount();
        $hasMonto = (bool)$pdo->query("SHOW COLUMNS FROM facturas LIKE 'monto'")->rowCount();
        $hasMontoTotal = (bool)$pdo->query("SHOW COLUMNS FROM facturas LIKE 'monto_total'")->rowCount();
    } catch (Throwable $e) {
        // Si falla la detección, continuamos con inserción estándar y dejamos que el catch global maneje errores
    }

    if ($hasMonto && $hasPlanId) {
        // Esquema: tiene columnas monto y plan_id
        $if = $pdo->prepare('INSERT INTO facturas (pedido_id, plan_id, monto, estado_pago) VALUES (?,?,?,"pendiente")');
        $if->execute([$pedidoId, (int)$pedido['plan_id'], $monto]);
    } elseif ($hasMonto && !$hasPlanId) {
        // Esquema: sólo tiene columna monto
        $if = $pdo->prepare('INSERT INTO facturas (pedido_id, monto, estado_pago) VALUES (?,?, "pendiente")');
        $if->execute([$pedidoId, $monto]);
    } elseif ($hasMontoTotal && $hasPlanId) {
        // Esquema: tiene columnas monto_total y plan_id (monto_total puede ser generado)
        $if = $pdo->prepare('INSERT INTO facturas (pedido_id, plan_id, monto_total, estado_pago) VALUES (?,?,?,"pendiente")');
        $if->execute([$pedidoId, (int)$pedido['plan_id'], $monto]);
    } elseif ($hasMontoTotal && !$hasPlanId) {
        // Esquema: sólo tiene columna monto_total
        $if = $pdo->prepare('INSERT INTO facturas (pedido_id, monto_total, estado_pago) VALUES (?,?, "pendiente")');
        $if->execute([$pedidoId, $monto]);
    } else {
        throw new Exception('Esquema de la tabla facturas no soportado.');
    }

    $fid = (int)$pdo->lastInsertId();

    // Leer fecha para generar código
    $rf = $pdo->prepare('SELECT fecha_factura FROM facturas WHERE id = ?');
    $rf->execute([$fid]);
    $fecha = (string)$rf->fetchColumn();
    $codigo = $genCode($fid, $fecha);

    $pdo->commit();
    $_SESSION['flash_success'] = 'Factura generada. Código: '.$codigo.' | Monto: $'.$monto;
    // Redirigir según destino solicitado
    if (!empty($returnToPago)) {
        header('Location: /cliente/pago.php?factura_id='.(int)$fid);
    } else {
        header('Location: /cliente/factura.php?factura_id='.(int)$fid);
    }
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_error'] = 'No se pudo generar la factura. Inténtalo de nuevo.';
}
// En caso de error, volver al dashboard
header('Location: /cliente/dashboard.php');
exit;