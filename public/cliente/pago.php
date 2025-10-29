<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('cliente');
require_once __DIR__.'/../../app/config/db.php';

$facturaId = isset($_GET['factura_id']) ? (int)$_GET['factura_id'] : 0;

if (!($pdo instanceof PDO)) {
  $_SESSION['flash_error'] = 'Error de conexión a la base de datos.';
  header('Location: /cliente/dashboard.php');
  exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

try {
  // Obtener cliente del usuario
  $st = $pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1');
  $st->execute([$userId]);
  $clienteId = (int)($st->fetchColumn() ?: 0);
  if ($clienteId <= 0) { throw new RuntimeException('Cliente no encontrado.'); }

  // Validar factura del cliente
  $q = $pdo->prepare('SELECT f.id AS factura_id, f.estado_pago, f.fecha_factura, p.id AS pedido_id, p.cliente_id, p.total, pl.nombre_plan AS plan_nombre FROM facturas f INNER JOIN pedidos p ON p.id=f.pedido_id LEFT JOIN planes pl ON pl.id = p.plan_id WHERE f.id=? AND p.cliente_id=? LIMIT 1');
  $q->execute([$facturaId, $clienteId]);
  $factura = $q->fetch(PDO::FETCH_ASSOC) ?: null;
  if (!$factura) { throw new RuntimeException('Factura no encontrada o no pertenece a tu cuenta.'); }

  $estado = strtolower((string)($factura['estado_pago'] ?? 'pendiente'));
  if ($estado !== 'pendiente') {
    $_SESSION['flash_error'] = 'Esta factura no está pendiente de pago.';
    header('Location: /cliente/factura.php?factura_id='.$facturaId);
    exit;
  }
} catch (Throwable $e) {
  $_SESSION['flash_error'] = 'No se pudo cargar la información de pago.';
  header('Location: /cliente/dashboard.php');
  exit;
}

// CSRF
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$token = $_SESSION['csrf'];

$planNombre = (string)($factura['plan_nombre'] ?? 'Servicio contratado');
$total = (float)($factura['total'] ?? 0);

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pagar factura #<?php echo (int)$facturaId; ?> - Revive tu Hogar</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    :root { --beige-principal:#E6D8C3; --beige-claro:#F5EFE6; --beige-oscuro:#D2B48C; --marron-elegante:#8C6A4F; --gris-carbon:#2C2C2C; --verde-oliva:#6B705C; --blanco-hueso:#FAF9F6; }
    body { background:#ffffff; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color:var(--gris-carbon); }
    .container { max-width:920px; margin:32px auto; padding:0 20px; display:grid; gap:24px; }
    .card { background:white; border-radius:16px; padding:24px; border:1px solid var(--beige-principal); box-shadow:0 4px 20px rgba(140,106,79,.08); }
    h1 { font-size:22px; color:var(--marron-elegante); margin:0; }
    .summary { display:flex; justify-content:space-between; gap:20px; }
    .summary .item { flex:1; }
    .methods { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; }
    .method { border:1px solid var(--beige-principal); border-radius:12px; padding:16px; background:var(--blanco-hueso); }
    .actions { display:flex; gap:8px; justify-content:flex-end; }
    .btn { background: linear-gradient(135deg, var(--marron-elegante) 0%, var(--verde-oliva) 100%); color:white; border:none; padding:12px 20px; border-radius:12px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
    .btn-secondary { background: linear-gradient(135deg, var(--beige-principal) 0%, var(--beige-oscuro) 100%); color: var(--gris-carbon); }
    .radio { display:flex; align-items:center; gap:10px; font-weight:600; }
    .subtle { color:var(--verde-oliva); }
  </style>
</head>
<body>
  <a class="btn btn-secondary" style="max-width:fit-content" href="/cliente/dashboard.php">← Volver al dashboard</a>
  <div class="container">
    <div class="card">
      <h1>Selecciona tu método de pago</h1>
      <p class="subtle">Factura #<?php echo (int)$facturaId; ?> · <?php echo htmlspecialchars($planNombre, ENT_QUOTES, 'UTF-8'); ?> · Total: $<?php echo number_format($total, 0, ',', '.'); ?></p>
    </div>

    <form class="card" method="post" action="/cliente/pago_iniciar.php">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" />
      <input type="hidden" name="factura_id" value="<?php echo (int)$facturaId; ?>" />

      <div class="methods">
        <label class="method radio">
          <input type="radio" name="metodo" value="paypal" required />
          <span>PayPal</span>
        </label>
        <label class="method radio">
          <input type="radio" name="metodo" value="bancolombia" />
          <span>Bancolombia (transferencia)</span>
        </label>
        <label class="method radio">
          <input type="radio" name="metodo" value="pse" />
          <span>PSE</span>
        </label>
        <label class="method radio">
          <input type="radio" name="metodo" value="tarjeta" />
          <span>Tarjeta de crédito/débito</span>
        </label>
      </div>

      <div class="actions" style="margin-top:12px;">
        <button class="btn" type="submit"><i class="fas fa-credit-card"></i> Confirmar y continuar</button>
        <a class="btn btn-secondary" href="/cliente/factura.php?factura_id=<?php echo (int)$facturaId; ?>">Ver factura</a>
      </div>
      <p class="subtle" style="margin-top:8px;">Al confirmar, registramos tu método y te llevamos a la factura.</p>
    </form>
  </div>
  <script>
    // nada por ahora
  </script>
</body>
</html>