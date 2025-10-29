<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('empleado');
require_once __DIR__.'/../../app/config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'Método inválido']);
  exit;
}

$csrf = $_POST['csrf'] ?? '';
if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrf)) {
  echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
  exit;
}

if (!($pdo instanceof PDO)) {
  echo json_encode(['ok' => false, 'error' => 'BD no disponible']);
  exit;
}

try {
  $userId = (int)($_SESSION['user_id'] ?? 0);
  $st = $pdo->prepare('SELECT id FROM empleados WHERE usuario_id = ? LIMIT 1');
  $st->execute([$userId]);
  $empleadoId = (int)($st->fetchColumn() ?: 0);
  if ($empleadoId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Empleado no encontrado']);
    exit;
  }

  $actividadId = (int)($_POST['actividad_id'] ?? 0);
  $resultado    = trim((string)($_POST['resultado'] ?? ''));
  if ($actividadId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID de actividad inválido']);
    exit;
  }

  // Asegurar existencia de tabla actividades (por si aún no se ha creado)
  try {
    $pdo->exec('CREATE TABLE IF NOT EXISTS actividades (
      id INT AUTO_INCREMENT PRIMARY KEY,
      tipo ENUM("nota", "llamada", "email", "reunion", "tarea", "seguimiento") DEFAULT "nota",
      descripcion TEXT NOT NULL,
      cliente_id INT,
      pedido_id INT NULL,
      empleado_id INT,
      fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      fecha_programada DATETIME NULL,
      estado ENUM("pendiente", "completada", "cancelada") DEFAULT "pendiente",
      prioridad ENUM("baja", "media", "alta") DEFAULT "media",
      resultado TEXT NULL,
      INDEX idx_cliente (cliente_id),
      INDEX idx_pedido (pedido_id),
      INDEX idx_empleado (empleado_id),
      INDEX idx_fecha (fecha_creacion)
    )');
  } catch (Throwable $e) { /* continuar */ }

  // Verificar permisos: el empleado debe ser el asignado a la actividad
  // o estar asignado al pedido relacionado
  $stmt = $pdo->prepare('SELECT a.id, a.empleado_id, a.pedido_id, a.estado FROM actividades a WHERE a.id = ? LIMIT 1');
  $stmt->execute([$actividadId]);
  $actividad = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  if (!$actividad) {
    echo json_encode(['ok' => false, 'error' => 'Actividad no encontrada']);
    exit;
  }

  $asignadoActividad = (int)($actividad['empleado_id'] ?? 0);
  $pedidoId = (int)($actividad['pedido_id'] ?? 0);
  $permitido = false;

  if ($asignadoActividad === $empleadoId) {
    $permitido = true;
  } elseif ($pedidoId > 0) {
    $sp = $pdo->prepare('SELECT empleado_id FROM pedidos WHERE id = ? LIMIT 1');
    $sp->execute([$pedidoId]);
    $empleadoPedido = (int)($sp->fetchColumn() ?: 0);
    if ($empleadoPedido === $empleadoId) {
      $permitido = true;
    }
  }

  if (!$permitido) {
    echo json_encode(['ok' => false, 'error' => 'Sin permiso para actualizar esta actividad']);
    exit;
  }

  if (($actividad['estado'] ?? '') === 'completada') {
    echo json_encode(['ok' => true, 'msg' => 'Ya estaba completada']);
    exit;
  }

  $upd = $pdo->prepare('UPDATE actividades SET estado = "completada", resultado = CASE WHEN ? <> "" THEN ? ELSE resultado END WHERE id = ?');
  $upd->execute([$resultado, $resultado, $actividadId]);

  echo json_encode(['ok' => true, 'msg' => 'Actividad marcada como completada']);
} catch (Throwable $e) {
  echo json_encode(['ok' => false, 'error' => 'Error: '.$e->getMessage()]);
}

exit;
?>