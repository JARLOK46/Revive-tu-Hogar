<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('cliente');
require_once __DIR__.'/../../app/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Location: /cliente/dashboard.php');
    exit;
}

$token = $_POST['csrf'] ?? '';
if (!$token || !isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
    $_SESSION['flash_error'] = 'Token CSRF inválido.';
    header('Location: /cliente/dashboard.php');
    exit;
}

$nombre   = trim((string)($_POST['nombre'] ?? ''));
$apellido = trim((string)($_POST['apellido'] ?? ''));
$telefono = trim((string)($_POST['telefono'] ?? ''));
$direccion= trim((string)($_POST['direccion'] ?? ''));
$userId   = (int)($_SESSION['user_id'] ?? 0);

if (!($pdo instanceof PDO)) {
    $_SESSION['flash_error'] = 'Error de conexión a la base de datos.';
    header('Location: /cliente/dashboard.php');
    exit;
}

try {
    // Asegurar cliente asociado al usuario
    $st = $pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1');
    $st->execute([$userId]);
    $clienteId = (int)($st->fetchColumn() ?: 0);

    if (!$clienteId) {
        // obtener email del usuario para crear cliente
        $u = $pdo->prepare('SELECT correo_electronico FROM usuarios WHERE id = ? LIMIT 1');
        $u->execute([$userId]);
        $email = (string)($u->fetchColumn() ?: '');
        $ins = $pdo->prepare('INSERT INTO clientes (nombre, apellido, correo, telefono, direccion, fecha_registro, usuario_id) VALUES (?,?,?,?,?, NOW(), ?)');
        $ins->execute(['', '', $email, '', '', $userId]);
        $clienteId = (int)$pdo->lastInsertId();
    }

    $upd = $pdo->prepare('UPDATE clientes SET nombre = ?, apellido = ?, telefono = ?, direccion = ? WHERE id = ?');
    $upd->execute([$nombre, $apellido, $telefono, $direccion, $clienteId]);

    $_SESSION['flash_success'] = 'Datos actualizados correctamente.';
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'No se pudo actualizar el perfil. Inténtalo más tarde.';
}

header('Location: /cliente/dashboard.php');
exit;