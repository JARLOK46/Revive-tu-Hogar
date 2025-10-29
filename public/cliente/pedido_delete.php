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

if (!($pdo instanceof PDO)) {
    $_SESSION['flash_error'] = 'Error de conexión a la base de datos.';
    header('Location: /cliente/dashboard.php');
    exit;
}

$pedidoId = (int)($_POST['pedido_id'] ?? 0);
$userId   = (int)($_SESSION['user_id'] ?? 0);

if ($pedidoId <= 0) {
    $_SESSION['flash_error'] = 'Pedido no válido.';
    header('Location: /cliente/dashboard.php');
    exit;
}

try {
    // Obtener cliente del usuario
    $st = $pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1');
    $st->execute([$userId]);
    $clienteId = (int)($st->fetchColumn() ?: 0);

    if ($clienteId <= 0) {
        $_SESSION['flash_error'] = 'No se encontró el cliente asociado.';
        header('Location: /cliente/dashboard.php');
        exit;
    }

    // Verificar que el pedido pertenece al cliente
    $chk = $pdo->prepare('SELECT id FROM pedidos WHERE id = ? AND cliente_id = ? LIMIT 1');
    $chk->execute([$pedidoId, $clienteId]);
    $exists = (int)($chk->fetchColumn() ?: 0);

    if ($exists <= 0) {
        $_SESSION['flash_error'] = 'No tienes permiso para eliminar este pedido.';
        header('Location: /cliente/dashboard.php');
        exit;
    }

    $pdo->beginTransaction();
    // Borrar datos relacionados si existieran
    $delF = $pdo->prepare('DELETE FROM facturas WHERE pedido_id = ?');
    $delF->execute([$pedidoId]);

    $delD = $pdo->prepare('DELETE FROM detallespedidos WHERE pedido_id = ?');
    $delD->execute([$pedidoId]);

    $delP = $pdo->prepare('DELETE FROM pedidos WHERE id = ?');
    $delP->execute([$pedidoId]);
    $pdo->commit();

    $_SESSION['flash_success'] = 'Pedido eliminado correctamente.';
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['flash_error'] = 'No se pudo eliminar el pedido. Inténtalo más tarde.';
}

header('Location: /cliente/dashboard.php');
exit;