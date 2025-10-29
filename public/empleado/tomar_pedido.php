<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('empleado');
require_once __DIR__.'/../../app/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /empleado/dashboard.php'); exit; }
$csrf = $_POST['csrf'] ?? '';
if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  $_SESSION['flash_error'] = 'CSRF inválido.'; header('Location: /empleado/dashboard.php'); exit;
}
$pedidoId = (int)($_POST['pedido_id'] ?? 0);
if ($pedidoId <= 0 || !($pdo instanceof PDO)) { $_SESSION['flash_error'] = 'Solicitud inválida.'; header('Location: /empleado/dashboard.php'); exit; }

try {
  $userId = (int)($_SESSION['user_id'] ?? 0);
  $se = $pdo->prepare('SELECT id FROM empleados WHERE usuario_id=? LIMIT 1');
  $se->execute([$userId]);
  $empleadoId = (int)($se->fetchColumn() ?: 0);
  if ($empleadoId <= 0) { $_SESSION['flash_error'] = 'Empleado no encontrado.'; header('Location: /empleado/dashboard.php'); exit; }

  $u = $pdo->prepare('UPDATE pedidos SET empleado_id=? WHERE id=? AND (empleado_id IS NULL OR empleado_id=?)');
  $u->execute([$empleadoId, $pedidoId, $empleadoId]);
  if ($u->rowCount() === 0) {
    $_SESSION['flash_error'] = 'El pedido ya fue tomado por otro empleado.';
  } else {
    $_SESSION['flash_success'] = 'Has tomado el pedido correctamente.';
  }
 } catch (Throwable $e) {
  $_SESSION['flash_error'] = 'No se pudo tomar el pedido.';
}

header('Location: /empleado/dashboard.php');
exit;