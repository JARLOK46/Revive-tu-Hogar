<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('empleado');
require_once __DIR__.'/../../app/config/db.php';

$flash_error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$flash_success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$userId = (int)($_SESSION['user_id'] ?? 0);
$pedidoId = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;

$empleadoId = 0;
$pedidoValido = false;
$cliente = null;
$clienteId = 0;

if (!($pdo instanceof PDO)) {
  $flash_error = $flash_error ?: 'Error de conexión a la base de datos.';
} else {
  try {
    // Empleado por usuario
    $st = $pdo->prepare('SELECT id, nombre FROM empleados WHERE usuario_id = ? LIMIT 1');
    $st->execute([$userId]);
    $empleado = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    $empleadoId = (int)($empleado['id'] ?? 0);

    if ($empleadoId && $pedidoId) {
      // Verificar pertenencia/permiso: asignado a este empleado o sin asignar
      $q = $pdo->prepare('SELECT p.id, p.cliente_id, p.empleado_id, c.nombre, c.apellido FROM pedidos p LEFT JOIN clientes c ON c.id = p.cliente_id WHERE p.id = ? LIMIT 1');
      $q->execute([$pedidoId]);
      $row = $q->fetch(PDO::FETCH_ASSOC) ?: null;
      if ($row) {
        $clienteId = (int)($row['cliente_id'] ?? 0);
        $cliente = $row;
        $asignadoA = (int)($row['empleado_id'] ?? 0);
        $pedidoValido = ($asignadoA === 0 || $asignadoA === $empleadoId);
        if (!$pedidoValido) {
          $flash_error = 'No tienes permiso para ver los archivos de este pedido.';
        }
      } else {
        $flash_error = 'Pedido no encontrado.';
      }
    }
  } catch (Throwable $e) {
    $flash_error = 'No se pudo validar el pedido.';
  }
}

// Preparar listado de archivos
$uploadBaseWeb = '/uploads/clientes/'.$clienteId.'/pedidos/'.$pedidoId;
$uploadBaseFs = dirname(__DIR__, 1) . $uploadBaseWeb; // public/empleado -> public
$archivos = [];
if ($pedidoValido) {
  if (!is_dir($uploadBaseFs)) {
    @mkdir($uploadBaseFs, 0775, true);
  }
  if (is_dir($uploadBaseFs)) {
    $items = @scandir($uploadBaseFs) ?: [];
    foreach ($items as $fn) {
      if ($fn === '.' || $fn === '..') continue;
      $fp = $uploadBaseFs.DIRECTORY_SEPARATOR.$fn;
      if (is_file($fp)) {
        $archivos[] = [
          'name' => $fn,
          'size' => @filesize($fp) ?: 0,
          'mtime' => @filemtime($fp) ?: 0,
        ];
      }
    }
    usort($archivos, function($a,$b){ return ($b['mtime']??0) <=> ($a['mtime']??0); });
  }
}

$nombreCliente = trim((string)($cliente['nombre'] ?? '').' '.(string)($cliente['apellido'] ?? ''));
$nombreCliente = $nombreCliente !== '' ? $nombreCliente : 'Cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Archivos del pedido #<?php echo (int)$pedidoId; ?> - Panel de Empleado</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    .files-container { max-width: 920px; margin: 24px auto; display: grid; gap: 16px; }
    .table { width: 100%; border-collapse: collapse; }
    .table th, .table td { padding: 10px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    .table th { background: #f9fafb; font-weight: 600; }
    .muted { color: #6b7280; font-size: 12px; }
  </style>
</head>
<body>
  <a href="#main" class="skip-link">Saltar al contenido</a>
  <header class="navbar" style="display:flex;justify-content:space-between;align-items:center;gap:8px;padding:8px 12px;border-radius:12px;margin-bottom:8px">
    <a class="back-link" href="/empleado/dashboard.php#pedido-<?php echo (int)$pedidoId; ?>">← Volver al dashboard</a>
  </header>
  <main id="main" class="files-container" tabindex="-1">
    <div class="card">
      <h2>Archivos del pedido #<?php echo (int)$pedidoId; ?></h2>
      <div class="muted">Cliente: <?php echo htmlspecialchars($nombreCliente, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <?php if ($flash_error): ?>
      <div class="alert error" role="alert"><?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($flash_success): ?>
      <div class="alert success" role="status"><?php echo htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$pedidoValido): ?>
      <div class="alert error" role="alert">Pedido inválido o no tienes permiso para ver sus archivos.</div>
    <?php else: ?>
      <div class="card">
        <h3>Archivos del cliente</h3>
        <?php if (empty($archivos)): ?>
          <p class="subtle">Aún no hay archivos para este pedido.</p>
        <?php else: ?>
          <table class="table">
            <thead>
              <tr>
                <th>Archivo</th>
                <th style="width:140px">Tamaño</th>
                <th style="width:200px">Modificado</th>
                <th style="width:120px"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($archivos as $f): ?>
                <?php
                  $fn = (string)($f['name'] ?? '');
                  $size = (int)($f['size'] ?? 0);
                  $mtime = (int)($f['mtime'] ?? 0);
                  $sizeStr = $size >= 1048576 ? (round($size/1048576,2).' MB') : (round(max($size,1)/1024,1).' KB');
                ?>
                <tr>
                  <td><a href="<?php echo htmlspecialchars($uploadBaseWeb.'/'.$fn, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" download><?php echo htmlspecialchars($fn, ENT_QUOTES, 'UTF-8'); ?></a></td>
                  <td><?php echo htmlspecialchars($sizeStr, ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars(date('Y-m-d H:i', $mtime), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <a class="btn btn-secondary" href="<?php echo htmlspecialchars($uploadBaseWeb.'/'.$fn, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" download>Descargar</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;justify-content:flex-end">
      <a class="btn btn-secondary" href="/empleado/dashboard.php">Volver al dashboard</a>
    </div>
  </main>
</body>
</html>