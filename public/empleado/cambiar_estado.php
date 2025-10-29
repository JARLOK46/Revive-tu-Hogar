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
$accion   = strtolower(trim((string)($_POST['accion'] ?? '')));
if ($pedidoId <= 0 || !in_array($accion, ['iniciar','completar','cancelar'], true) || !($pdo instanceof PDO)) {
  $_SESSION['flash_error'] = 'Solicitud inválida.'; header('Location: /empleado/dashboard.php'); exit;
}

// Asegurar tabla de historial (DDL fuera de la transacción para evitar commits implícitos)
try {
  $pdo->exec(
    'CREATE TABLE IF NOT EXISTS historial_estados (
       id INT AUTO_INCREMENT PRIMARY KEY,
       pedido_id INT NOT NULL,
       estado_anterior VARCHAR(50) NOT NULL,
       estado_nuevo VARCHAR(50) NOT NULL,
       empleado_id INT NOT NULL,
       motivo VARCHAR(255) DEFAULT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       INDEX (pedido_id),
       CONSTRAINT fk_historial_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
  );
} catch (Throwable $e) {
  // No bloquear por fallo creando historial; solo registrar aviso y continuar
  // En un log real usaríamos error_log($e->getMessage());
}

try {
  $pdo->beginTransaction();

  // Empleado actual
  $userId = (int)($_SESSION['user_id'] ?? 0);
  $se = $pdo->prepare('SELECT id FROM empleados WHERE usuario_id=? LIMIT 1');
  $se->execute([$userId]);
  $empleadoId = (int)($se->fetchColumn() ?: 0);
  if ($empleadoId <= 0) { throw new Exception('Empleado no encontrado.'); }

  // Cargar pedido y factura
  $sp = $pdo->prepare('SELECT p.id, p.estado, p.empleado_id, f.id AS factura_id, f.estado_pago FROM pedidos p LEFT JOIN facturas f ON f.pedido_id = p.id WHERE p.id = ? LIMIT 1');
  $sp->execute([$pedidoId]);
  $row = $sp->fetch(PDO::FETCH_ASSOC) ?: null;
  if (!$row) { throw new Exception('Pedido no encontrado.'); }

  $estadoActual = strtolower((string)($row['estado'] ?? ''));
  $asignadoA    = (int)($row['empleado_id'] ?? 0);
  $estadoPago   = strtolower((string)($row['estado_pago'] ?? 'pendiente'));

  if (in_array($estadoActual, ['entregado','cancelado'], true)) {
    throw new Exception('El pedido ya no admite cambios de estado.');
  }

  $nuevoEstado = null;

  // Reglas de transición
  switch ($accion) {
    case 'iniciar':
      if ($estadoActual !== 'pendiente') {
        throw new Exception('Solo se puede iniciar un pedido pendiente.');
      }
      if ($asignadoA && $asignadoA !== $empleadoId) {
        throw new Exception('El pedido pertenece a otro empleado.');
      }
      if (!$asignadoA) {
        // Auto-asignar si está libre
        $u = $pdo->prepare('UPDATE pedidos SET empleado_id = ?, estado = "enviado" WHERE id = ? AND (empleado_id IS NULL OR empleado_id = ?)');
        $u->execute([$empleadoId, $pedidoId, $empleadoId]);
        if ($u->rowCount() === 0) { throw new Exception('No se pudo iniciar: el pedido fue tomado por otro empleado.'); }
      } else {
        $u = $pdo->prepare('UPDATE pedidos SET estado = "enviado" WHERE id = ?');
        $u->execute([$pedidoId]);
      }
      $nuevoEstado = 'enviado';
      $_SESSION['flash_success'] = 'Pedido iniciado.';
      break;

    case 'completar':
      if (!in_array($estadoActual, ['enviado','pendiente'], true)) {
        throw new Exception('Solo se puede completar un pedido en curso o pendiente.');
      }
      if ($asignadoA !== $empleadoId) {
        throw new Exception('Solo el empleado asignado puede completar el pedido.');
      }
      if ($estadoPago !== 'pagado') {
        throw new Exception('No se puede completar: la factura aún no está pagada.');
      }
      $u = $pdo->prepare('UPDATE pedidos SET estado = "entregado" WHERE id = ?');
      $u->execute([$pedidoId]);
      $nuevoEstado = 'entregado';
      $_SESSION['flash_success'] = 'Pedido marcado como entregado.';
      break;

    case 'cancelar':
      if (!in_array($estadoActual, ['pendiente','enviado'], true)) {
        throw new Exception('Solo se puede cancelar un pedido pendiente o en curso.');
      }
      if ($asignadoA && $asignadoA !== $empleadoId) {
        throw new Exception('Solo el empleado asignado puede cancelar este pedido.');
      }
      $u = $pdo->prepare('UPDATE pedidos SET estado = "cancelado" WHERE id = ?');
      $u->execute([$pedidoId]);
      $nuevoEstado = 'cancelado';
      $_SESSION['flash_success'] = 'Pedido cancelado.';
      break;
  }

  // Registrar historial si se determinó un nuevo estado
  if ($nuevoEstado) {
    $ins = $pdo->prepare('INSERT INTO historial_estados (pedido_id, estado_anterior, estado_nuevo, empleado_id, motivo) VALUES (?,?,?,?,?)');
    $ins->execute([$pedidoId, $estadoActual, $nuevoEstado, $empleadoId, null]);
  }

  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  $_SESSION['flash_error'] = $e->getMessage() ?: 'No se pudo actualizar el estado.';
}

header('Location: /empleado/dashboard.php');
exit;