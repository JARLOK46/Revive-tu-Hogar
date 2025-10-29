<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('cliente');
require_once __DIR__.'/../../app/config/db.php';

// Verificar conexión a la base de datos
if (!($pdo instanceof PDO)) {
    $_SESSION['flash_error'] = 'Error de conexión a la base de datos.';
    header('Location: /planes.php');
    exit;
}

$planParam = trim((string)($_GET['plan'] ?? ''));
$planId = ctype_digit($planParam) ? (int)$planParam : null;

if (!$planId) {
    // Compatibilidad con slugs antiguos (opcional)
    $slugMap = ['esencial'=>1,'confort'=>2,'premium'=>3];
    $planId = $slugMap[strtolower($planParam)] ?? null;
}

if (!$planId) {
    $_SESSION['flash_error'] = 'Plan seleccionado no válido.';
    header('Location: /planes.php');
    exit;
}
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    // Obtener/crear cliente asociado (misma lógica que en dashboard)
    $st = $pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1');
    $st->execute([$userId]);
    $clienteId = (int)($st->fetchColumn() ?: 0);

    if (!$clienteId) {
        $usr = $pdo->prepare('SELECT correo_electronico FROM usuarios WHERE id = ? LIMIT 1');
        $usr->execute([$userId]);
        $email = (string)($usr->fetchColumn() ?: '');
        $ins = $pdo->prepare('INSERT INTO clientes (nombre, apellido, correo, telefono, direccion, fecha_registro, usuario_id) VALUES (?,?,?,?,?, NOW(), ?)');
        $ins->execute(['','', $email, '', '', $userId]);
        $clienteId = (int)$pdo->lastInsertId();
    }

    // Obtener precio del plan
    $p = $pdo->prepare('SELECT precio, nombre_plan FROM planes WHERE id = ? LIMIT 1');
    $p->execute([$planId]);
    $plan = $p->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$plan) { throw new RuntimeException('Plan no encontrado'); }

    // Crear pedido
    $insP = $pdo->prepare('INSERT INTO pedidos (cliente_id, plan_id, empleado_id, fecha, total, estado) VALUES (?, ?, NULL, NOW(), ?, "pendiente")');
    $insP->execute([$clienteId, $planId, (float)$plan['precio']]);

    $_SESSION['flash_success'] = 'Tu pedido del plan "'.$plan['nombre_plan'].'" ha sido creado. Nos pondremos en contacto.';
    header('Location: /cliente/dashboard.php');
    exit;
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'No se pudo procesar tu contratación. Intenta nuevamente.';
    header('Location: /planes.php');
    exit;
}