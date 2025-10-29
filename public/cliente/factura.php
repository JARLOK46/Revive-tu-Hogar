<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('cliente');
require_once __DIR__.'/../../app/config/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$facturaId = isset($_GET['factura_id']) ? (int)$_GET['factura_id'] : 0;

function generar_codigo_factura($facturaId, $fechaFactura){
  if (!$facturaId || !$fechaFactura) return null;
  $salt = 'RTH-SALT-2024';
  $base = $facturaId.'|'.substr((string)$fechaFactura, 0, 10).'|'.$salt;
  return strtoupper(substr(hash('sha256', $base), 0, 8));
}

$factura = null;
$error = null;

if (!($pdo instanceof PDO)) {
  $error = 'Error de conexión a la base de datos.';
} elseif ($facturaId <= 0) {
  $error = 'Parámetro factura_id inválido.';
} else {
  try {
    // Obtener cliente por usuario
    $st = $pdo->prepare('SELECT id FROM clientes WHERE usuario_id = ? LIMIT 1');
    $st->execute([$userId]);
    $clienteId = (int)($st->fetchColumn() ?: 0);

    if (!$clienteId) {
      $error = 'No se encontró el perfil del cliente.';
    } else {
      // Cargar factura con validación de pertenencia
      $q = $pdo->prepare('SELECT f.id AS factura_id, f.fecha_factura, f.estado_pago, p.id AS pedido_id, p.fecha AS pedido_fecha, p.total, p.estado AS pedido_estado, c.nombre, c.apellido, c.correo, c.telefono, c.direccion, pl.nombre_plan AS plan_nombre FROM facturas f INNER JOIN pedidos p ON p.id = f.pedido_id INNER JOIN clientes c ON c.id = p.cliente_id LEFT JOIN planes pl ON pl.id = p.plan_id WHERE f.id = ? AND p.cliente_id = ? LIMIT 1');
      $q->execute([$facturaId, $clienteId]);
      $factura = $q->fetch(PDO::FETCH_ASSOC) ?: null;
      if (!$factura) {
        $error = 'Factura no encontrada o no pertenece a tu cuenta.';
      }
    }
  } catch (Throwable $e) {
    $error = 'No se pudo cargar la factura.';
  }
}

$codigo = $factura ? generar_codigo_factura((int)$factura['factura_id'], $factura['fecha_factura'] ?? '') : null;
$nombreCliente = trim(($factura['nombre'] ?? '').' '.($factura['apellido'] ?? ''));
$nombreCliente = $nombreCliente !== '' ? $nombreCliente : 'Cliente';

// Redirigir a selección de pago si la factura está pendiente y no hay pago registrado
if ($factura && strtolower((string)($factura['estado_pago'] ?? 'pendiente')) !== 'pagado') {
  $shouldRedirect = true;
  if ($pdo instanceof PDO) {
    try {
      $hasPagos = (bool)$pdo->query("SHOW TABLES LIKE 'pagos'")->rowCount();
      if ($hasPagos) {
        $stP = $pdo->prepare('SELECT id FROM pagos WHERE factura_id = ? LIMIT 1');
        $stP->execute([$facturaId]);
        $existsPago = (int)($stP->fetchColumn() ?: 0);
        if ($existsPago) { $shouldRedirect = false; }
      }
    } catch (Throwable $e) { /* silencioso */ }
  }
  if ($shouldRedirect) {
    header('Location: /cliente/pago.php?factura_id='.(int)$facturaId);
    exit;
  }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Factura #<?php echo htmlspecialchars((string)$facturaId, ENT_QUOTES, 'UTF-8'); ?> - Revive tu Hogar</title>
  <link rel="stylesheet" href="../assets/css/styles.css" />
  <style>
    :root {
      --beige-principal: #E6D8C3;
      --beige-claro: #F5EFE6;
      --beige-oscuro: #D2B48C;
      --marron-elegante: #8C6A4F;
      --gris-carbon: #2C2C2C;
      --verde-oliva: #6B705C;
      --blanco-hueso: #FAF9F6;
    }

    body {
      background: #ffffff;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: var(--gris-carbon);
    }

    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; }
      .card { box-shadow: none !important; border: 1px solid #e5e7eb !important; }
      .invoice { margin: 0 !important; }
    }

    .invoice {
      max-width: 920px;
      margin: 32px auto;
      display: grid;
      gap: 24px;
      padding: 0 20px;
    }

    .invoice-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
      padding: 32px;
      background: linear-gradient(135deg, var(--blanco-hueso) 0%, var(--beige-claro) 100%);
      border-radius: 16px;
      border: 1px solid var(--beige-oscuro);
      box-shadow: 0 4px 20px rgba(140, 106, 79, 0.1);
    }

    .invoice-brand {
      font-weight: 800;
      font-size: 28px;
      letter-spacing: 0.5px;
      color: var(--marron-elegante);
      margin-bottom: 8px;
    }

    .invoice-company-info {
      color: var(--verde-oliva);
      font-size: 14px;
      line-height: 1.5;
    }

    .invoice-meta {
      text-align: right;
      background: white;
      padding: 20px;
      border-radius: 12px;
      border: 1px solid var(--beige-principal);
      box-shadow: 0 2px 8px rgba(140, 106, 79, 0.05);
    }

    .invoice-meta > div {
      margin-bottom: 8px;
      font-size: 14px;
    }

    .invoice-meta > div:last-child {
      margin-bottom: 0;
    }

    .invoice-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
    }

    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid var(--beige-claro);
    }

    .info-item:last-child {
      border-bottom: none;
    }

    .info-label {
      font-weight: 600;
      color: var(--verde-oliva);
    }

    .info-value {
      color: var(--gris-carbon);
      text-align: right;
    }

    .no-data {
      color: var(--verde-oliva);
      font-style: italic;
      text-align: center;
      padding: 20px;
    }

    .card {
      background: white;
      border-radius: 16px;
      padding: 24px;
      border: 1px solid var(--beige-principal);
      box-shadow: 0 4px 20px rgba(140, 106, 79, 0.08);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(140, 106, 79, 0.12);
    }

    .card h3 {
      color: var(--marron-elegante);
      font-size: 18px;
      font-weight: 700;
      margin: 0 0 16px 0;
      padding-bottom: 8px;
      border-bottom: 2px solid var(--beige-claro);
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(140, 106, 79, 0.05);
    }

    .table th {
      background: linear-gradient(135deg, var(--beige-principal) 0%, var(--beige-oscuro) 100%);
      color: var(--gris-carbon);
      font-weight: 700;
      padding: 16px;
      text-align: left;
      font-size: 14px;
      letter-spacing: 0.5px;
    }

    .table td {
      padding: 16px;
      border-bottom: 1px solid var(--beige-claro);
      background: white;
    }

    .table tbody tr:hover {
      background: var(--blanco-hueso);
    }

    .total-row td {
      font-weight: 700;
      font-size: 16px;
      background: var(--beige-claro) !important;
      color: var(--marron-elegante);
    }

    .badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .badge.success {
      background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
      color: #059669;
      border: 1px solid #a7f3d0;
    }

    .badge.warning {
      background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
      color: #b45309;
      border: 1px solid #fde68a;
    }

    .badge:not(.success):not(.warning) {
      background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
      color: #4f46e5;
      border: 1px solid #c7d2fe;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      color: var(--marron-elegante);
      text-decoration: none;
      font-weight: 600;
      padding: 12px 20px;
      background: var(--blanco-hueso);
      border-radius: 12px;
      border: 1px solid var(--beige-principal);
      transition: all 0.2s ease;
      margin-bottom: 20px;
      max-width: fit-content;
    }

    .back-link:hover {
      background: var(--beige-claro);
      transform: translateX(-4px);
      box-shadow: 0 4px 12px rgba(140, 106, 79, 0.1);
    }

    .btn {
      background: linear-gradient(135deg, var(--marron-elegante) 0%, var(--verde-oliva) 100%);
      color: white;
      border: none;
      padding: 12px 24px;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
      display: inline-block;
      font-size: 14px;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(140, 106, 79, 0.3);
    }

    .btn-secondary {
      background: linear-gradient(135deg, var(--beige-principal) 0%, var(--beige-oscuro) 100%);
      color: var(--gris-carbon);
    }

    .btn-secondary:hover {
      box-shadow: 0 6px 20px rgba(210, 180, 140, 0.3);
    }

    .alert {
      padding: 16px 20px;
      border-radius: 12px;
      margin: 16px 0;
      border-left: 4px solid;
      font-weight: 500;
    }

    .alert.error {
      background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
      border-left-color: #ef4444;
      color: #dc2626;
    }

    .subtle {
      color: var(--verde-oliva);
      font-size: 14px;
    }

    .terms {
      font-size: 0.9em;
      color: var(--gris-carbon);
      line-height: 1.6;
    }

    .term-item {
      display: flex;
      align-items: center;
      margin: 12px 0;
      padding: 8px 0;
    }

    .term-item i {
      color: var(--verde-oliva);
      margin-right: 12px;
      width: 16px;
      text-align: center;
    }

    .term-item span {
      flex: 1;
    }

    @media (max-width: 768px) {
      .invoice {
        margin: 16px auto;
        padding: 0 16px;
      }

      .invoice-header {
        flex-direction: column;
        gap: 16px;
        padding: 24px;
      }

      .invoice-meta {
        text-align: left;
      }

      .invoice-brand {
        font-size: 24px;
      }

      .card {
        padding: 20px;
      }

      .table th, .table td {
        padding: 12px 8px;
        font-size: 13px;
      }
    }
  </style>
</head>
<body>
  <a class="back-link no-print" href="/cliente/dashboard.php">← Volver a mi cuenta</a>
  <div class="invoice">
    <div class="invoice-header">
      <div>
        <div class="invoice-brand">Revive tu Hogar</div>
        <div class="invoice-company-info">
          <div>www.revivetuhogar.com</div>
          <div>contacto@revivetuhogar.com</div>
          <div>Armenia, Quindío - Colombia</div>
        </div>
      </div>
      <div class="invoice-meta">
        <div><strong>Factura #</strong><?php echo (int)$facturaId; ?></div>
        <?php if ($factura): ?>
        <div><strong>Fecha:</strong> <?php echo htmlspecialchars(substr((string)($factura['fecha_factura'] ?? ''),0,10), ENT_QUOTES, 'UTF-8'); ?></div>
        <div><strong>Estado de pago:</strong> <span class="badge <?php echo (($factura['estado_pago'] ?? '')==='pagado')?'success':((($factura['estado_pago'] ?? '')==='pendiente')?'warning':''); ?>"><?php echo htmlspecialchars($factura['estado_pago'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span></div>
        <?php endif; ?>
        <?php if ($codigo): ?><div class="subtle"><strong>Código:</strong> <?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
      </div>
    </div>

    <div class="invoice-grid">
      <div class="card">
        <h3>Información del Cliente</h3>
        <div class="client-info">
          <?php if ($factura): ?>
          <div class="info-item">
            <span class="info-label">Nombre:</span>
            <span class="info-value"><?php echo htmlspecialchars($nombreCliente, ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Email:</span>
            <span class="info-value"><?php echo htmlspecialchars($factura['correo'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Teléfono:</span>
            <span class="info-value"><?php echo htmlspecialchars($factura['telefono'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Dirección:</span>
            <span class="info-value"><?php echo htmlspecialchars($factura['direccion'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <?php else: ?>
          <div class="no-data">No se encontró información del cliente.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="card">
        <h3>Detalles del Servicio</h3>
        <div class="service-info">
          <?php if ($factura): ?>
          <div class="info-item">
            <span class="info-label">Servicio:</span>
            <span class="info-value"><?php echo htmlspecialchars($factura['servicio'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Fecha del servicio:</span>
            <span class="info-value"><?php echo htmlspecialchars(substr((string)($factura['fecha_servicio'] ?? ''),0,10), ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <div class="info-item">
            <span class="info-label">Descripción:</span>
            <span class="info-value"><?php echo htmlspecialchars($factura['descripcion'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
          </div>
          <?php else: ?>
          <div class="no-data">No se encontraron detalles del servicio.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <h3>Detalles de la Factura</h3>
      <div class="invoice-table-container">
        <table class="table">
          <thead>
            <tr>
              <th>Descripción</th>
              <th>Cantidad</th>
              <th>Precio Unitario</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($factura): ?>
            <tr>
              <td><?php echo htmlspecialchars(($factura['plan_nombre'] ?? 'Servicio contratado'), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>1</td>
              <td>$<?php echo number_format((float)($factura['total'] ?? 0), 0, ',', '.'); ?></td>
              <td>$<?php echo number_format((float)($factura['total'] ?? 0), 0, ',', '.'); ?></td>
            </tr>
            <tr class="total-row">
              <td colspan="3"><strong>Subtotal:</strong></td>
              <td><strong>$<?php echo $factura ? number_format((float)($factura['total'] ?? 0), 0, ',', '.') : '0'; ?></strong></td>
            </tr>
            <tr class="total-row">
              <td colspan="3"><strong>IVA (19%):</strong></td>
              <td><strong>$<?php echo $factura ? number_format((float)($factura['total'] ?? 0) * 0.19, 0, ',', '.') : '0'; ?></strong></td>
            </tr>
            <tr class="total-row">
              <td colspan="3"><strong>Total Final:</strong></td>
              <td><strong>$<?php echo $factura ? number_format((float)($factura['total'] ?? 0) * 1.19, 0, ',', '.') : '0'; ?></strong></td>
            </tr>
            <?php else: ?>
            <tr>
              <td colspan="4" class="text-center">No se encontraron detalles de la factura.</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h3>Términos y Condiciones</h3>
      <div class="terms">
        <div class="term-item">
          <i class="fas fa-clock"></i>
          <span>El pago debe realizarse dentro de los 30 días posteriores a la fecha de la factura.</span>
        </div>
        <div class="term-item">
          <i class="fas fa-shield-alt"></i>
          <span>Los servicios prestados están garantizados por 6 meses.</span>
        </div>
        <div class="term-item">
          <i class="fas fa-headset"></i>
          <span>Para cualquier consulta, contacte a nuestro equipo de atención al cliente.</span>
        </div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="alert error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="no-print" style="display:flex;gap:8px;justify-content:flex-end">
      <button class="btn" onclick="window.print()">Imprimir / Guardar como PDF</button>
      <a class="btn btn-secondary" href="/cliente/dashboard.php">Volver al dashboard</a>
    </div>
  </div>

  <script>
    // Opcional: auto-abrir diálogo de impresión si se pasa ?print=1
    (function(){
      const params = new URLSearchParams(location.search);
      if (params.get('print') === '1') {
        setTimeout(() => window.print(), 300);
      }
    })();
  </script>
</body>
</html>