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
$codigoIn = trim((string)($_POST['codigo'] ?? ''));
if ($pedidoId <= 0 || $codigoIn === '' || !($pdo instanceof PDO)) { $_SESSION['flash_error'] = 'Solicitud inválida.'; header('Location: /empleado/dashboard.php'); exit; }

// Helper de código
function generar_codigo_factura($facturaId, $fechaFactura) {
  if (!$facturaId || !$fechaFactura) return null;
  $salt = 'RTH-SALT-2024';
  $base = $facturaId.'|'.substr((string)$fechaFactura, 0, 10).'|'.$salt;
  return strtoupper(substr(hash('sha256', $base), 0, 8));
}

try {
  $pdo->beginTransaction();

  // Obtener empleado
  $userId = (int)($_SESSION['user_id'] ?? 0);
  $se = $pdo->prepare('SELECT id FROM empleados WHERE usuario_id=? LIMIT 1');
  $se->execute([$userId]);
  $empleadoId = (int)($se->fetchColumn() ?: 0);
  if ($empleadoId <= 0) { throw new Exception('Empleado no encontrado.'); }

  // Obtener pedido y factura
  $sp = $pdo->prepare('SELECT p.id, p.empleado_id, f.id AS factura_id, f.estado_pago, f.fecha_factura FROM pedidos p LEFT JOIN facturas f ON f.pedido_id=p.id WHERE p.id=? LIMIT 1');
  $sp->execute([$pedidoId]);
  $row = $sp->fetch(PDO::FETCH_ASSOC) ?: null;
  if (!$row || empty($row['factura_id'])) { throw new Exception('Factura no encontrada para el pedido.'); }
  if (($row['estado_pago'] ?? 'pendiente') === 'pagado') { throw new Exception('El pago ya fue confirmado.'); }

  // Validar código
  $codigoEsperado = generar_codigo_factura((int)$row['factura_id'], $row['fecha_factura'] ?? '');
  if (strtoupper($codigoIn) !== $codigoEsperado) { throw new Exception('Código inválido.'); }

  // Si el pedido no tiene empleado, asignar a este empleado
  if (empty($row['empleado_id'])) {
    $up = $pdo->prepare('UPDATE pedidos SET empleado_id=? WHERE id=?');
    $up->execute([$empleadoId, $pedidoId]);
  }

  // Marcar factura pagada
  $uf = $pdo->prepare('UPDATE facturas SET estado_pago="pagado" WHERE id=?');
  $uf->execute([(int)$row['factura_id']]);

  $pdo->commit();
  $_SESSION['flash_success'] = 'Pago confirmado con éxito.';
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  $_SESSION['flash_error'] = $e->getMessage() ?: 'No se pudo confirmar el pago.';
}

header('Location: /empleado/dashboard.php');
exit;