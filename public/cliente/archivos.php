<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('cliente');
require_once __DIR__.'/../../app/config/db.php';

$flash_error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$flash_success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

// CSRF token
$token = bin2hex(random_bytes(16));
$_SESSION['csrf'] = $token;

$userId = (int)($_SESSION['user_id'] ?? 0);
$pedidoId = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;

$clienteId = 0;
$pedidoValido = false;
$cliente = null;

if (!($pdo instanceof PDO)) {
  $flash_error = $flash_error ?: 'Error de conexión a la base de datos.';
} else {
  try {
    // Cliente por usuario
    $st = $pdo->prepare('SELECT id, nombre, apellido FROM clientes WHERE usuario_id = ? LIMIT 1');
    $st->execute([$userId]);
    $cliente = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    $clienteId = (int)($cliente['id'] ?? 0);

    if ($clienteId && $pedidoId) {
      $q = $pdo->prepare('SELECT id FROM pedidos WHERE id = ? AND cliente_id = ? LIMIT 1');
      $q->execute([$pedidoId, $clienteId]);
      $pedidoValido = (bool)$q->fetchColumn();
    }
  } catch (Throwable $e) {
    $flash_error = 'No se pudo validar el pedido.';
  }
}

// Preparar listado de archivos
$uploadBaseWeb = '/uploads/clientes/'.$clienteId.'/pedidos/'.$pedidoId;
$uploadBaseFs = dirname(__DIR__, 1) . $uploadBaseWeb; // public/cliente -> public
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

$nombreCliente = trim(($cliente['nombre'] ?? '').' '.($cliente['apellido'] ?? ''));
$nombreCliente = $nombreCliente !== '' ? $nombreCliente : 'Cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Archivos del pedido #<?php echo (int)$pedidoId; ?> - Revive tu Hogar</title>
  <script>
    (function(){
      try {
        var stored = localStorage.getItem('theme');
        var prefers = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        var theme = stored || prefers;
        document.documentElement.setAttribute('data-theme', theme);
      } catch(e) {}
    })();
  </script>
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
    <a class="back-link" href="/cliente/dashboard.php#pedido-<?php echo (int)$pedidoId; ?>">← Volver al dashboard</a>
  </header>
  <main id="main" class="files-container" tabindex="-1">
    <div class="card">
      <h2>Archivos del pedido #<?php echo (int)$pedidoId; ?></h2>
      <div class="muted">Cuenta: <?php echo htmlspecialchars($nombreCliente, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <?php if ($flash_error): ?>
      <div class="alert error" role="alert"><?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($flash_success): ?>
      <div class="alert success" role="status"><?php echo htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if (!$pedidoValido): ?>
      <div class="alert error" role="alert">Pedido inválido o no pertenece a tu cuenta.</div>
    <?php else: ?>
      <div class="card">
        <h3>Subir archivos</h3>
        <form method="post" action="/cliente/archivo_subir.php" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="pedido_id" value="<?php echo (int)$pedidoId; ?>">
          <input class="input" type="file" name="archivos[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt" aria-label="Seleccionar archivos" />
          <button class="btn btn-secondary" type="submit">Subir</button>
          <span class="muted">Tamaño máx. por archivo: 10 MB</span>
        </form>
      </div>

      <div class="card">
        <h3>Mis archivos</h3>
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
                    <form method="post" action="/cliente/archivo_eliminar.php" onsubmit="return confirm('¿Eliminar este archivo?');" style="display:inline-block">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="pedido_id" value="<?php echo (int)$pedidoId; ?>">
                      <input type="hidden" name="filename" value="<?php echo htmlspecialchars($fn, ENT_QUOTES, 'UTF-8'); ?>">
                      <button class="btn btn-danger" type="submit">Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div style="display:flex;gap:8px;justify-content:flex-end">
      <a class="btn" href="/cliente/dashboard.php#pedido-<?php echo (int)$pedidoId; ?>">Volver al dashboard</a>
    </div>
  </main>
  <script>
    (function(){
      var btn = document.getElementById('themeToggle');
      if(!btn) return;
      var root = document.documentElement;
      function apply(theme){
        root.setAttribute('data-theme', theme);
        try{ localStorage.setItem('theme', theme); }catch(e){}
        var pressed = theme === 'dark';
        btn.setAttribute('aria-pressed', pressed.toString());
      }
      btn.addEventListener('click', function(){
        var current = root.getAttribute('data-theme') || 'light';
        apply(current === 'dark' ? 'light' : 'dark');
      });
      try {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        mq.addEventListener ? mq.addEventListener('change', function(e){
          if(!localStorage.getItem('theme')) apply(e.matches ? 'dark' : 'light');
        }) : mq.addListener && mq.addListener(function(e){
          if(!localStorage.getItem('theme')) apply(e.matches ? 'dark' : 'light');
        });
      } catch(e) {}
      apply(document.documentElement.getAttribute('data-theme') || 'light');
    })();
  </script>
</body>
</html>