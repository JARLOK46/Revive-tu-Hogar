<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('cliente');
require_once __DIR__.'/../../app/config/db.php';

// CSRF para formularios
$token = bin2hex(random_bytes(16));
$_SESSION['csrf'] = $token;

$flash_error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$flash_success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$userId = (int)($_SESSION['user_id'] ?? 0);
$cliente = null; $clienteId = null; $userEmail = '';
$pedidos = [];

// Variables para CRM del cliente
$notificaciones_cliente = [];
$actividades_cliente = [];
$metricas_cliente = [
    'total_pedidos' => 0,
    'total_gastado' => 0,
    'pedidos_completados' => 0,
    'pedidos_pendientes' => 0,
    'ultimo_pedido' => null,
    'plan_favorito' => null,
    'tiempo_cliente' => 0
];

if (!($pdo instanceof PDO)) {
    // Sin conexión a BD: mostrar alerta y evitar consultas
    $flash_error = $flash_error ?: 'Error de conexión a la base de datos.';
    $cliente = ['id' => 0, 'nombre' => '', 'apellido' => '', 'correo' => '', 'telefono' => '', 'direccion' => ''];
} else {
    try {
        // Email del usuario
        $st = $pdo->prepare('SELECT correo_electronico FROM usuarios WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $userEmail = (string)($st->fetchColumn() ?: '');

        // 1) Buscar cliente por usuario_id
        $st = $pdo->prepare('SELECT id, nombre, apellido, correo, telefono, direccion FROM clientes WHERE usuario_id = ? LIMIT 1');
        $st->execute([$userId]);
        $cliente = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        // 2) Si no existe, intentar enlazar por correo
        if (!$cliente && $userEmail) {
            $sel = $pdo->prepare('SELECT id, nombre, apellido, correo, telefono, direccion, usuario_id FROM clientes WHERE correo = ? LIMIT 1');
            $sel->execute([$userEmail]);
            $tmp = $sel->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($tmp) {
                if (empty($tmp['usuario_id'])) {
                    $upd = $pdo->prepare('UPDATE clientes SET usuario_id = ? WHERE id = ?');
                    $upd->execute([$userId, (int)$tmp['id']]);
                }
                $cliente = [
                    'id' => (int)$tmp['id'],
                    'nombre' => $tmp['nombre'] ?? '',
                    'apellido' => $tmp['apellido'] ?? '',
                    'correo' => $tmp['correo'] ?? $userEmail,
                    'telefono' => $tmp['telefono'] ?? '',
                    'direccion' => $tmp['direccion'] ?? ''
                ];
            }
        }

        // 3) Si aún no existe, crear cliente mínimo
        if (!$cliente) {
            $ins = $pdo->prepare('INSERT INTO clientes (nombre, apellido, correo, telefono, direccion, fecha_registro, usuario_id) VALUES (?,?,?,?,?, NOW(), ?)');
            $ins->execute(['', '', $userEmail, '', '', $userId]);
            $newId = (int)$pdo->lastInsertId();
            $cliente = ['id' => $newId, 'nombre' => '', 'apellido' => '', 'correo' => $userEmail, 'telefono' => '', 'direccion' => ''];
        }

        $clienteId = (int)$cliente['id'];

        // Pedidos del cliente
        if ($clienteId) {
            $q = $pdo->prepare('SELECT p.id, p.fecha, p.total, p.estado, pl.nombre_plan AS plan_nombre, f.id AS factura_id, f.fecha_factura, f.estado_pago, e.nombre AS empleado_nombre FROM pedidos p LEFT JOIN planes pl ON pl.id = p.plan_id LEFT JOIN facturas f ON f.pedido_id = p.id LEFT JOIN empleados e ON e.id = p.empleado_id WHERE p.cliente_id = ? ORDER BY p.fecha DESC');
            $q->execute([$clienteId]);
            $pedidos = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Calcular métricas CRM del cliente
            $metricas_cliente['total_pedidos'] = count($pedidos);
            $metricas_cliente['total_gastado'] = array_sum(array_column($pedidos, 'total'));
            $metricas_cliente['pedidos_completados'] = count(array_filter($pedidos, function($p) { return $p['estado'] === 'entregado'; }));
            $metricas_cliente['pedidos_pendientes'] = count(array_filter($pedidos, function($p) { return in_array($p['estado'], ['pendiente', 'enviado']); }));
            
            if (!empty($pedidos)) {
                $metricas_cliente['ultimo_pedido'] = $pedidos[0];
                
                // Plan favorito (más usado)
                $planes_count = [];
                foreach ($pedidos as $p) {
                    $plan = $p['plan_nombre'] ?? 'Sin plan';
                    $planes_count[$plan] = ($planes_count[$plan] ?? 0) + 1;
                }
                if (!empty($planes_count)) {
                    $metricas_cliente['plan_favorito'] = array_keys($planes_count, max($planes_count))[0];
                }
                
                // Tiempo como cliente
                $primer_pedido = end($pedidos);
                if ($primer_pedido && $primer_pedido['fecha']) {
                    $fecha_inicio = new DateTime($primer_pedido['fecha']);
                    $fecha_actual = new DateTime();
                    $metricas_cliente['tiempo_cliente'] = $fecha_inicio->diff($fecha_actual)->days;
                }
            }

            // Obtener actividades relacionadas con el cliente
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

                $act_query = $pdo->prepare('SELECT a.*, e.nombre AS empleado_nombre FROM actividades a LEFT JOIN empleados e ON e.id = a.empleado_id WHERE a.cliente_id = ? ORDER BY a.fecha_creacion DESC LIMIT 10');
                $act_query->execute([$clienteId]);
                $actividades_cliente = $act_query->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $actividades_cliente = [];
            }
        }
    } catch (Throwable $e) {
        $flash_error = 'No se pudieron cargar tus datos. Inténtalo más tarde.';
    }
}

// Generar notificaciones personalizadas para el cliente
$notificaciones_cliente = [];

// Notificación de bienvenida personalizada
if ($metricas_cliente['tiempo_cliente'] < 30) {
    $notificaciones_cliente[] = [
        'tipo' => 'success',
        'icono' => '🎉',
        'titulo' => '¡Bienvenido a Revive tu Hogar!',
        'mensaje' => 'Gracias por confiar en nosotros para transformar tu espacio',
        'accion' => '#servicios',
        'tiempo' => 'Nuevo'
    ];
} else {
    $notificaciones_cliente[] = [
        'tipo' => 'info',
        'icono' => '💎',
        'titulo' => '¡Cliente VIP!',
        'mensaje' => "Llevas {$metricas_cliente['tiempo_cliente']} días con nosotros. ¡Gracias por tu confianza!",
        'accion' => '#historial',
        'tiempo' => 'Especial'
    ];
}

// Notificación de estado de pedidos
if ($metricas_cliente['pedidos_pendientes'] > 0) {
    $notificaciones_cliente[] = [
        'tipo' => 'warning',
        'icono' => '🚧',
        'titulo' => 'Pedidos en progreso',
        'mensaje' => "Tienes {$metricas_cliente['pedidos_pendientes']} pedidos en proceso. Te mantendremos informado",
        'accion' => '#pedidos',
        'tiempo' => 'Activo'
    ];
}

// Notificación de último pedido
if ($metricas_cliente['ultimo_pedido']) {
    $ultimo = $metricas_cliente['ultimo_pedido'];
    $dias_ultimo = (new DateTime())->diff(new DateTime($ultimo['fecha']))->days;
    
    if ($dias_ultimo < 7) {
        $notificaciones_cliente[] = [
            'tipo' => 'info',
            'icono' => '📦',
            'titulo' => 'Pedido reciente',
            'mensaje' => "Tu último pedido ({$ultimo['plan_nombre']}) está {$ultimo['estado']}",
            'accion' => '#pedidos',
            'tiempo' => $dias_ultimo === 0 ? 'Hoy' : "Hace {$dias_ultimo} días"
        ];
    }
}

// Notificación de plan favorito
if ($metricas_cliente['plan_favorito'] && $metricas_cliente['total_pedidos'] > 1) {
    $notificaciones_cliente[] = [
        'tipo' => 'success',
        'icono' => '⭐',
        'titulo' => 'Tu plan favorito',
        'mensaje' => "El plan '{$metricas_cliente['plan_favorito']}' es tu preferido. ¿Quieres ver más opciones similares?",
        'accion' => '#planes',
        'tiempo' => 'Recomendación'
    ];
}

// Notificación de actividades recientes
if (!empty($actividades_cliente)) {
    $actividades_pendientes = array_filter($actividades_cliente, function($a) { return $a['estado'] === 'pendiente'; });
    if (!empty($actividades_pendientes)) {
        $notificaciones_cliente[] = [
            'tipo' => 'info',
            'icono' => '📞',
            'titulo' => 'Comunicaciones pendientes',
            'mensaje' => count($actividades_pendientes) . ' actividades programadas con tu equipo de trabajo',
            'accion' => '#comunicaciones',
            'tiempo' => 'Programado'
        ];
    }
}

// Si no hay actividades, crear algunas de ejemplo
if (empty($actividades_cliente) && $clienteId) {
    $actividades_cliente = [
        [
            'id' => 'demo1',
            'tipo' => 'llamada',
            'descripcion' => 'Seguimiento de satisfacción del proyecto',
            'empleado_nombre' => 'Equipo de Atención',
            'fecha_creacion' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'estado' => 'completada'
        ],
        [
            'id' => 'demo2',
            'tipo' => 'email',
            'descripcion' => 'Envío de catálogo de nuevos diseños',
            'empleado_nombre' => 'Equipo de Diseño',
            'fecha_creacion' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'estado' => 'completada'
        ],
        [
            'id' => 'demo3',
            'tipo' => 'reunion',
            'descripcion' => 'Consulta para próximo proyecto',
            'empleado_nombre' => 'Consultor Especializado',
            'fecha_programada' => date('Y-m-d H:i:s', strtotime('+3 days')),
            'estado' => 'pendiente'
        ]
    ];
}
 // Helper para generar código de factura determinístico sin almacenar en BD
 function generar_codigo_factura($facturaId, $fechaFactura)
 {
     if (!$facturaId || !$fechaFactura) return null;
     $salt = 'RTH-SALT-2024';
     $base = $facturaId.'|'.substr((string)$fechaFactura, 0, 10).'|'.$salt;
     return strtoupper(substr(hash('sha256', $base), 0, 8));
 }
 
 // Historial por pedido (agrupado)
 $historialPorPedido = [];
 if (($pdo instanceof PDO) && !empty($pedidos)) {
     try {
        // Asegurar existencia de la tabla historial_estados por si aún no ha sido creada en flujos del empleado
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
        } catch (Throwable $e) { /* continuar sin bloquear */ }
        $ids = array_map('intval', array_column($pedidos, 'id'));
         if (!empty($ids)) {
             $placeholders = implode(',', array_fill(0, count($ids), '?'));
             $sqlH = "SELECT h.pedido_id, h.estado_anterior, h.estado_nuevo, h.motivo, h.created_at, e.nombre AS empleado_nombre\n                      FROM historial_estados h\n                      LEFT JOIN empleados e ON e.id = h.empleado_id\n                      WHERE h.pedido_id IN ($placeholders)\n                      ORDER BY h.created_at DESC, h.id DESC";
             $stH = $pdo->prepare($sqlH);
             $stH->execute($ids);
             $rows = $stH->fetchAll(PDO::FETCH_ASSOC) ?: [];
             foreach ($rows as $r) {
                 $pid = (int)($r['pedido_id'] ?? 0);
                 if ($pid <= 0) continue;
                 if (!isset($historialPorPedido[$pid])) { $historialPorPedido[$pid] = []; }
                 $historialPorPedido[$pid][] = $r;
             }
         }
    } catch (Throwable $e) { /* sin bloqueo si falla historial */ }
 }
// KPIs básicos (mejora incremental)
$kpi_total = is_array($pedidos) ? count($pedidos) : 0;
$kpi_en_progreso = 0; $kpi_completado = 0; $kpi_pendiente = 0;
foreach ($pedidos as $pp) {
    $est = strtolower((string)($pp['estado'] ?? ''));
    if (strpos($est, 'entreg') !== false) { $kpi_completado++; }
    elseif (strpos($est, 'enviad') !== false) { $kpi_en_progreso++; }
    elseif (strpos($est, 'pend') !== false) { $kpi_pendiente++; }
    else { $kpi_pendiente++; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi cuenta - Revive tu Hogar</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <script src="../assets/js/cliente-dashboard.js" defer></script>
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
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .dashboard-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }

    .dashboard-header {
      background: var(--blanco-hueso);
      border-radius: 16px;
      padding: 32px;
      margin-bottom: 24px;
      box-shadow: 0 4px 20px rgba(140, 106, 79, 0.1);
      border: 1px solid var(--beige-oscuro);
    }

    .dashboard-title {
      font-size: 2.5rem;
      font-weight: 700;
      margin: 0;
      color: var(--gris-carbon);
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .dashboard-subtitle {
      margin: 12px 0 0;
      color: var(--marron-elegante);
      font-size: 1.1rem;
    }

    .alert-modern {
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 20px;
      border-left: 4px solid;
      font-weight: 500;
    }

    .alert-modern.error {
      background: rgba(239, 68, 68, 0.1);
      border-left-color: #ef4444;
      color: #dc2626;
    }

    .alert-modern.success {
      background: rgba(34, 197, 94, 0.1);
      border-left-color: #22c55e;
      color: #16a34a;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: var(--blanco-hueso);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(140, 106, 79, 0.1);
      border: 1px solid var(--beige-oscuro);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--verde-oliva);
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 30px rgba(140, 106, 79, 0.15);
    }

    .stat-icon {
      width: 48px;
      height: 48px;
      background: var(--verde-oliva);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--blanco-hueso);
      font-size: 24px;
      margin-bottom: 16px;
    }

    .stat-number {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--gris-carbon);
      margin-bottom: 8px;
    }

    .stat-label {
      color: var(--marron-elegante);
      font-weight: 500;
      font-size: 0.95rem;
    }

    .main-content {
      display: flex;
      flex-direction: column;
      gap: 24px;
    }

    .section-card {
      background: var(--blanco-hueso);
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(140, 106, 79, 0.1);
      border: 1px solid var(--beige-oscuro);
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0 0 20px;
      color: var(--gris-carbon);
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .filters-modern {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      align-items: center;
      margin-bottom: 24px;
      padding: 20px;
      background: var(--beige-claro);
      border-radius: 12px;
      border: 1px solid var(--beige-oscuro);
    }

    .form-input {
      padding: 12px 16px;
      border: 2px solid var(--beige-oscuro);
      border-radius: 8px;
      background: var(--blanco-hueso);
      color: var(--gris-carbon);
      font-size: 14px;
      transition: all 0.3s ease;
      min-width: 200px;
    }

    .form-input:focus {
      outline: none;
      border-color: var(--verde-oliva);
      box-shadow: 0 0 0 3px rgba(107, 112, 92, 0.1);
    }

    .pedidos-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 20px;
    }

    .pedido-card {
      background: var(--blanco-hueso);
      border-radius: 12px;
      padding: 20px;
      border: 1px solid var(--beige-oscuro);
      transition: all 0.3s ease;
      position: relative;
    }

    .pedido-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(140, 106, 79, 0.15);
    }

    .pedido-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }

    .pedido-plan {
      font-weight: 600;
      color: var(--gris-carbon);
      font-size: 1.1rem;
    }

    .pedido-id {
      color: var(--marron-elegante);
      font-size: 0.9rem;
    }

    .pedido-info {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      align-items: center;
      margin-bottom: 16px;
    }

    .badge-modern {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .badge-modern.pendiente {
      background: rgba(245, 158, 11, 0.1);
      color: #d97706;
      border: 1px solid rgba(245, 158, 11, 0.3);
    }

    .badge-modern.enviado {
      background: rgba(59, 130, 246, 0.1);
      color: #2563eb;
      border: 1px solid rgba(59, 130, 246, 0.3);
    }

    .badge-modern.entregado {
      background: rgba(34, 197, 94, 0.1);
      color: #16a34a;
      border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .badge-modern.pagado {
      background: rgba(34, 197, 94, 0.1);
      color: #16a34a;
      border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .pedido-total {
      font-weight: 700;
      color: var(--verde-oliva);
      font-size: 1.2rem;
    }

    .pedido-actions {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 16px;
    }

    .btn-modern {
      padding: 8px 16px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .btn-modern.primary {
      background: var(--verde-oliva);
      color: var(--blanco-hueso);
    }

    .btn-modern.primary:hover {
      background: var(--marron-elegante);
      transform: translateY(-1px);
    }

    .btn-modern.secondary {
      background: var(--beige-oscuro);
      color: var(--gris-carbon);
    }

    .btn-modern.secondary:hover {
      background: var(--marron-elegante);
      color: var(--blanco-hueso);
    }

    .btn-modern.danger {
      background: #ef4444;
      color: white;
    }

    .btn-modern.danger:hover {
      background: #dc2626;
    }

    .history-item {
      background: var(--beige-claro);
      border-radius: 8px;
      padding: 12px;
      margin-bottom: 8px;
      border-left: 3px solid var(--verde-oliva);
    }

    .profile-form {
      max-width: 600px;
      margin: 0 auto;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--gris-carbon);
    }

    .form-group input {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid var(--beige-oscuro);
      border-radius: 8px;
      background: var(--blanco-hueso);
      color: var(--gris-carbon);
      font-size: 16px;
      transition: all 0.3s ease;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--verde-oliva);
      box-shadow: 0 0 0 3px rgba(107, 112, 92, 0.1);
    }

    .form-actions {
      display: flex;
      gap: 16px;
      flex-wrap: wrap;
      justify-content: center;
      margin-top: 24px;
    }

    /* Estilos para notificaciones del cliente */
    .client-notifications-panel {
      background: linear-gradient(135deg, var(--blanco-hueso) 0%, #f8f6f0 100%);
      border: 2px solid var(--verde-oliva);
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 32px;
      box-shadow: 0 8px 30px rgba(107, 112, 92, 0.15);
      position: relative;
      overflow: hidden;
    }

    .client-notifications-panel::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--verde-oliva), var(--beige-claro));
    }

    .notifications-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--beige-oscuro);
    }

    .notifications-header h3 {
      margin: 0;
      color: var(--gris-carbon);
      font-size: 1.5rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .notifications-badge {
      background: var(--verde-oliva);
      color: var(--blanco-hueso);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    .notifications-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 16px;
    }

    .client-notification-card {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 20px;
      border-radius: 16px;
      border: 1px solid;
      transition: all 0.3s ease;
      cursor: pointer;
      background: var(--blanco-hueso);
    }

    .client-notification-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .client-notification-card.notification-success {
      border-color: #22c55e;
      background: linear-gradient(135deg, rgba(34, 197, 94, 0.05) 0%, rgba(34, 197, 94, 0.1) 100%);
    }

    .client-notification-card.notification-info {
      border-color: #3b82f6;
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.1) 100%);
    }

    .client-notification-card.notification-warning {
      border-color: #f59e0b;
      background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(245, 158, 11, 0.1) 100%);
    }

    .client-notification-card.notification-error {
      border-color: #ef4444;
      background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(239, 68, 68, 0.1) 100%);
    }

    .notification-icon {
      font-size: 28px;
      flex-shrink: 0;
    }

    .notification-content {
      flex: 1;
    }

    .notification-title {
      font-weight: 700;
      color: var(--gris-carbon);
      margin-bottom: 6px;
      font-size: 1.1rem;
    }

    .notification-message {
      font-size: 14px;
      color: var(--marron-elegante);
      line-height: 1.4;
    }

    .notification-time {
      font-size: 12px;
      color: #999;
      font-weight: 600;
      background: rgba(255, 255, 255, 0.8);
      padding: 4px 8px;
      border-radius: 12px;
    }

    /* Estilos para comunicaciones */
    .client-communications-panel {
      background: linear-gradient(135deg, var(--blanco-hueso) 0%, #f8f6f0 100%);
      border: 2px solid var(--beige-claro);
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 32px;
      box-shadow: 0 8px 30px rgba(139, 115, 85, 0.15);
      position: relative;
      overflow: hidden;
    }

    .client-communications-panel::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--beige-claro), var(--verde-oliva));
    }

    .communications-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--beige-oscuro);
    }

    .communications-header h3 {
      margin: 0;
      color: var(--gris-carbon);
      font-size: 1.5rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .communications-count {
      background: var(--beige-claro);
      color: var(--marron-elegante);
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    .communications-timeline {
      position: relative;
      padding-left: 30px;
    }

    .communications-timeline::before {
      content: '';
      position: absolute;
      left: 15px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: linear-gradient(180deg, var(--verde-oliva), var(--beige-claro));
    }

    .communication-item {
      position: relative;
      margin-bottom: 24px;
      background: var(--blanco-hueso);
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(140, 106, 79, 0.08);
      border: 1px solid var(--beige-oscuro);
      transition: all 0.3s ease;
    }

    .communication-item:hover {
      transform: translateX(8px);
      box-shadow: 0 8px 25px rgba(140, 106, 79, 0.12);
    }

    .communication-item::before {
      content: '';
      position: absolute;
      left: -35px;
      top: 20px;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--verde-oliva);
      border: 3px solid var(--blanco-hueso);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .communication-icon {
      position: absolute;
      left: -45px;
      top: 15px;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      background: var(--verde-oliva);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      color: var(--blanco-hueso);
      box-shadow: 0 3px 10px rgba(107, 112, 92, 0.3);
    }

    .communication-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
    }

    .communication-type {
      font-weight: 700;
      color: var(--verde-oliva);
      text-transform: capitalize;
      font-size: 1rem;
    }

    .communication-date {
      font-size: 12px;
      color: #999;
      font-weight: 500;
    }

    .communication-description {
      margin-bottom: 16px;
      line-height: 1.5;
      color: var(--gris-carbon);
    }

    .communication-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .communication-employee {
      font-size: 13px;
      color: var(--marron-elegante);
      font-weight: 500;
    }

    .communication-status {
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
      text-transform: capitalize;
    }

    .status-pendiente {
      background: rgba(245, 158, 11, 0.1);
      color: #f59e0b;
      border: 1px solid rgba(245, 158, 11, 0.2);
    }

    .status-completada {
      background: rgba(34, 197, 94, 0.1);
      color: #22c55e;
      border: 1px solid rgba(34, 197, 94, 0.2);
    }

    .status-cancelada {
      background: rgba(239, 68, 68, 0.1);
      color: #ef4444;
      border: 1px solid rgba(239, 68, 68, 0.2);
    }

    /* Navegación por pestañas del cliente */
    .client-nav {
      display: flex;
      gap: 8px;
      margin: 24px 0;
      padding: 8px;
      background: linear-gradient(135deg, var(--beige-claro) 0%, #f0ede3 100%);
      border-radius: 16px;
      border: 1px solid var(--beige-oscuro);
      box-shadow: 0 4px 15px rgba(140, 106, 79, 0.1);
      overflow-x: auto;
    }

    .nav-tab {
      padding: 12px 20px;
      border-radius: 12px;
      text-decoration: none;
      color: var(--marron-elegante);
      font-weight: 600;
      font-size: 0.9rem;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
      border: 2px solid transparent;
      background: transparent;
      position: relative;
      overflow: hidden;
    }

    .nav-tab::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(107, 112, 92, 0.1), transparent);
      transition: left 0.5s ease;
    }

    .nav-tab:hover {
      background: linear-gradient(135deg, var(--blanco-hueso) 0%, #f8f6f0 100%);
      color: var(--verde-oliva);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(107, 112, 92, 0.15);
      border-color: var(--verde-oliva);
    }

    .nav-tab:hover::before {
      left: 100%;
    }

    .nav-tab.active {
      background: linear-gradient(135deg, var(--verde-oliva) 0%, #8b9a7a 100%);
      color: var(--blanco-hueso);
      box-shadow: 0 4px 15px rgba(107, 112, 92, 0.3);
      border-color: var(--verde-oliva);
      transform: translateY(-1px);
    }

    .nav-tab.active::before {
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    }

    .nav-tab i {
      font-size: 1rem;
    }

    /* Contenedores de pestañas */
    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
      animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 768px) {
      .dashboard-container {
        padding: 16px;
      }
      
      .dashboard-title {
        font-size: 2rem;
      }
      
      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      }
      
      .pedidos-grid {
        grid-template-columns: 1fr;
      }
      
      .filters-modern {
        flex-direction: column;
        align-items: stretch;
      }
      
      .form-input {
        min-width: auto;
        width: 100%;
      }

      .notifications-grid {
        grid-template-columns: 1fr;
      }

      .communications-timeline {
        padding-left: 20px;
      }

      .communication-icon {
        left: -35px;
      }

      .communication-item::before {
        left: -25px;
      }

      .communication-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }

      .client-nav {
        gap: 4px;
        padding: 6px;
      }

      .nav-tab {
        padding: 10px 16px;
        font-size: 0.85rem;
      }
    }
  </style>
</head>
<body>
  <a href="#main" class="skip-link">Saltar al contenido</a>
  <a class="back-link" href="/index.php" aria-label="Regresar al inicio">← Volver</a>
  
  <div class="dashboard-container">
    <main id="main" tabindex="-1">
      <header class="dashboard-header">
        <h1 class="dashboard-title">
          <i class="fas fa-user-circle"></i>
          Mi Dashboard
        </h1>
        <p class="dashboard-subtitle">
          Gestiona tus pedidos y mantén actualizada tu información
        </p>
      </header>

      <!-- Alertas -->
      <?php if ($flash_error): ?>
        <div class="alert-modern error" role="alert">
          <i class="fas fa-exclamation-triangle"></i>
          <?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>
      <?php if ($flash_success): ?>
        <div class="alert-modern success" role="status">
          <i class="fas fa-check-circle"></i>
          <?php echo htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <!-- Navegación por pestañas -->
      <div class="client-nav">
        <a href="#" class="nav-tab active" data-tab="tab-dashboard">
          <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="#" class="nav-tab" data-tab="tab-pedidos">
          <i class="fas fa-shopping-cart"></i> Mis Pedidos
        </a>
        <a href="#" class="nav-tab" data-tab="tab-comunicaciones">
          <i class="fas fa-comments"></i> Comunicaciones
        </a>
        <a href="#" class="nav-tab" data-tab="tab-perfil">
          <i class="fas fa-user-edit"></i> Mi Perfil
        </a>
      </div>

      <!-- Contenido de la pestaña Dashboard -->
      <div id="tab-dashboard" class="tab-content active">
        <!-- Panel de Notificaciones Personalizadas -->
        <?php if (!empty($notificaciones_cliente)): ?>
        <div class="client-notifications-panel">
          <div class="notifications-header">
            <h3><i class="fas fa-bell"></i> Tus Notificaciones</h3>
            <span class="notifications-badge"><?php echo count($notificaciones_cliente); ?></span>
          </div>
          <div class="notifications-grid">
            <?php foreach ($notificaciones_cliente as $notif): ?>
            <div class="client-notification-card notification-<?php echo htmlspecialchars($notif['tipo']); ?>">
              <div class="notification-icon"><?php echo $notif['icono']; ?></div>
              <div class="notification-content">
                <div class="notification-title"><?php echo htmlspecialchars($notif['titulo']); ?></div>
                <div class="notification-message"><?php echo htmlspecialchars($notif['mensaje']); ?></div>
              </div>
              <div class="notification-time"><?php echo htmlspecialchars($notif['tiempo']); ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Estadísticas CRM Mejoradas -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="stat-number"><?php echo (int)$metricas_cliente['total_pedidos']; ?></div>
            <div class="stat-label">Total Pedidos</div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-number">$<?php echo number_format($metricas_cliente['total_gastado'], 0); ?></div>
            <div class="stat-label">Total Invertido</div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-clock"></i>
            </div>
            <div class="stat-number"><?php echo (int)$metricas_cliente['pedidos_pendientes']; ?></div>
            <div class="stat-label">En Progreso</div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-number"><?php echo (int)$metricas_cliente['pedidos_completados']; ?></div>
            <div class="stat-label">Completados</div>
          </div>

          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-star"></i>
            </div>
            <div class="stat-number"><?php echo $metricas_cliente['plan_favorito'] ? '⭐' : '—'; ?></div>
            <div class="stat-label">Plan Favorito</div>
          </div>

          <div class="stat-card">
            <div class="stat-icon">
              <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-number"><?php echo $metricas_cliente['tiempo_cliente']; ?></div>
            <div class="stat-label">Días con Nosotros</div>
          </div>
        </div>
      </div>

      <!-- Contenido de la pestaña Pedidos -->
      <div id="tab-pedidos" class="tab-content">
        <div class="main-content">
        <!-- Gestión de Pedidos -->
        <section class="section-card">
          <h2 class="section-title">
            <i class="fas fa-list-alt"></i>
            Gestión de Pedidos
          </h2>
          
          <div class="filters-modern">
            <input class="form-input" type="search" id="filtroBusqueda" placeholder="Buscar por plan o #ID" aria-label="Buscar pedidos" />
            <select class="form-input" id="filtroEstado" aria-label="Filtrar por estado">
              <option value="">Estado: todos</option>
              <option value="pendiente">Pendiente</option>
              <option value="enviado">Enviado</option>
              <option value="entregado">Entregado</option>
            </select>
            <select class="form-input" id="filtroPago" aria-label="Filtrar por estado de pago">
              <option value="">Pago: todos</option>
              <option value="pagado">Pagado</option>
              <option value="pendiente">Pendiente</option>
            </select>
            <div class="subtle" id="filtroResumen" aria-live="polite" style="margin-left:auto; color: var(--marron-elegante); font-weight: 500;">Mostrando 0 de 0</div>
          </div>

          <?php if (!empty($pedidos)): ?>
            <div id="pedidosList" class="pedidos-grid">
              <?php foreach ($pedidos as $p): ?>
                <div class="pedido-card"
                     data-id="<?php echo (int)$p['id']; ?>"
                     data-plan="<?php echo htmlspecialchars($p['plan_nombre'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>"
                     data-estado="<?php echo htmlspecialchars($p['estado'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>"
                     data-pago="<?php echo htmlspecialchars($p['estado_pago'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>"
                     data-fecha="<?php echo htmlspecialchars($p['fecha'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                  
                  <div class="pedido-header">
                    <div class="pedido-plan"><?php echo htmlspecialchars($p['plan_nombre'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="pedido-id">#<?php echo (int)$p['id']; ?></div>
                  </div>
                  
                  <div class="pedido-info">
                    <span style="color: var(--marron-elegante); font-size: 0.9rem;"><?php echo htmlspecialchars($p['fecha'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="badge-modern <?php echo strtolower($p['estado'] ?? ''); ?>"><?php echo htmlspecialchars($p['estado'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="pedido-total">$<?php echo number_format((float)($p['total'] ?? 0), 2); ?></span>
                  </div>
                  
                  <div style="margin-bottom: 16px;">
                    <?php $codigo = isset($p['factura_id']) ? generar_codigo_factura((int)$p['factura_id'], $p['fecha_factura'] ?? '') : null; ?>
                    <?php if (($p['estado_pago'] ?? null) === 'pagado'): ?>
                      <span class="badge-modern pagado">Pagado</span>
                      <?php if ($codigo): ?><span style="color: var(--marron-elegante); font-size: 0.85rem;"> Código: <?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                    <?php elseif (($p['estado_pago'] ?? 'pendiente') === 'pendiente' && !empty($p['factura_id'])): ?>
                      <span class="badge-modern pendiente">Pendiente</span>
                      <?php if ($codigo): ?><span style="color: var(--marron-elegante); font-size: 0.85rem;"> Código: <?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                      <?php
                        $pg = null;
                        if (($pdo instanceof PDO) && !empty($p['factura_id'])) {
                          try {
                            $hasPagos = (bool)$pdo->query("SHOW TABLES LIKE 'pagos'")->rowCount();
                            if ($hasPagos) {
                              $stP = $pdo->prepare('SELECT metodo, estado, referencia FROM pagos WHERE factura_id = ? ORDER BY id DESC LIMIT 1');
                              $stP->execute([(int)$p['factura_id']]);
                              $pg = $stP->fetch(PDO::FETCH_ASSOC) ?: null;
                            }
                          } catch (Throwable $e) { $pg = null; }
                        }
                      ?>
                      <?php if (!empty($pg)): ?>
                        <div class="subtle" style="margin-top:6px;">Método: <?php echo htmlspecialchars(ucfirst($pg['metodo']), ENT_QUOTES, 'UTF-8'); ?> • Estado: <?php echo htmlspecialchars($pg['estado'], ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($pg['referencia'])): ?> • Ref: <?php echo htmlspecialchars($pg['referencia'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?></div>
                      <?php else: ?>
                        <div class="subtle" style="margin-top:6px;">Método: No seleccionado</div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge-modern">—</span>
                    <?php endif; ?>
                  </div>
                  
                  <div class="pedido-actions">
                    <form method="post" action="/cliente/pedido_delete.php" onsubmit="return confirm('¿Seguro que deseas eliminar este pedido? Esta acción no se puede deshacer.');">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                      <button class="btn-modern danger" type="submit">
                        <i class="fas fa-trash"></i> Eliminar
                      </button>
                    </form>
                    
                    <?php if (($p['estado_pago'] ?? '') !== 'pagado'): ?>
                      <?php if (empty($p['factura_id'])): ?>
                        <form method="post" action="/cliente/pago_crear.php">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                          <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                          <input type="hidden" name="return" value="pago">
                          <button class="btn-modern primary" type="submit">
                            <i class="fas fa-credit-card"></i> Pagar
                          </button>
                        </form>
                      <?php else: ?>
                        <a class="btn-modern primary" href="/cliente/pago.php?factura_id=<?php echo (int)$p['factura_id']; ?>">
                          <i class="fas fa-credit-card"></i> Pagar
                        </a>
                        <a class="btn-modern secondary" href="/cliente/factura.php?factura_id=<?php echo (int)$p['factura_id']; ?>">
                          <i class="fas fa-file-invoice"></i> Ver factura
                        </a>
                      <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (!empty($p['factura_id'])): ?>
                      <a class="btn-modern secondary" href="/cliente/factura.php?factura_id=<?php echo (int)$p['factura_id']; ?>&print=1" target="_blank" rel="noopener">
                        <i class="fas fa-download"></i> Descargar
                      </a>
                    <?php endif; ?>
                    
                    <a class="btn-modern secondary" href="/cliente/archivos.php?pedido_id=<?php echo (int)$p['id']; ?>">
                      <i class="fas fa-folder"></i> Archivos
                    </a>
                  </div>
                  
                  <details style="margin-top: 16px;">
                    <summary style="cursor: pointer; color: var(--verde-oliva); font-weight: 600;">Historial de estado</summary>
                    <?php $hist = $historialPorPedido[(int)$p['id']] ?? []; ?>
                    <?php if (!empty($hist)): ?>
                      <div style="margin-top: 12px;">
                        <?php foreach ($hist as $h): ?>
                          <div class="history-item">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                              <span class="badge-modern"><?php echo htmlspecialchars(strtoupper((string)($h['estado_anterior'] ?? ''))) . ' → ' . htmlspecialchars(strtoupper((string)($h['estado_nuevo'] ?? ''))); ?></span>
                              <span style="color: var(--marron-elegante); font-size: 0.85rem;"><?php echo htmlspecialchars(substr((string)($h['created_at'] ?? ''), 0, 16), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php if (!empty($h['empleado_nombre'])): ?>
                              <div style="color: var(--marron-elegante); font-size: 0.85rem;">Por: <?php echo htmlspecialchars((string)$h['empleado_nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($h['motivo'])): ?>
                              <div style="color: var(--gris-carbon); font-size: 0.9rem; margin-top: 4px;">Motivo: <?php echo htmlspecialchars((string)$h['motivo'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <p style="color: var(--marron-elegante); margin: 12px 0 0; font-style: italic;">Sin movimientos aún.</p>
                    <?php endif; ?>
                  </details>
                </div>
              <?php endforeach; ?>
            </div>
            
            <nav id="paginacionControles" class="pagination" aria-label="Paginación de pedidos" style="display:flex;gap:12px;align-items:center;justify-content:center;margin-top:24px;">
              <label style="color: var(--marron-elegante); font-weight: 500;" for="pageSize">Por página</label>
              <select id="pageSize" class="form-input" aria-label="Pedidos por página" style="min-width: auto; width: auto;">
                <option value="6">6</option>
                <option value="12" selected>12</option>
                <option value="24">24</option>
              </select>
              <span id="pageInfo" style="color: var(--marron-elegante); font-weight: 500;">0-0 de 0</span>
              <button id="pagePrev" class="btn-modern secondary" type="button" aria-label="Página anterior">Anterior</button>
              <button id="pageNext" class="btn-modern secondary" type="button" aria-label="Página siguiente">Siguiente</button>
            </nav>
          <?php else: ?>
            <div style="text-align: center; padding: 40px; color: var(--marron-elegante);">
              <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
              <p style="font-size: 1.1rem; margin: 0;">Aún no tienes pedidos.</p>
            </div>
          <?php endif; ?>
        </section>
        </div>
      </div>

      <!-- Contenido de la pestaña Comunicaciones -->
      <div id="tab-comunicaciones" class="tab-content">
        <!-- Panel de Comunicaciones -->
        <?php if (!empty($actividades_cliente)): ?>
        <div class="client-communications-panel">
          <div class="communications-header">
            <h3><i class="fas fa-comments"></i> Historial de Comunicaciones</h3>
            <span class="communications-count"><?php echo count($actividades_cliente); ?> registros</span>
          </div>
          <div class="communications-timeline">
            <?php foreach ($actividades_cliente as $actividad): ?>
            <div class="communication-item <?php echo htmlspecialchars($actividad['estado']); ?>">
              <div class="communication-icon">
                <?php 
                $icons = ['nota' => '📝', 'llamada' => '📞', 'email' => '📧', 'reunion' => '🤝', 'tarea' => '✅', 'seguimiento' => '🔄'];
                echo $icons[$actividad['tipo']] ?? '📄'; 
                ?>
              </div>
              <div class="communication-content">
                <div class="communication-header">
                  <span class="communication-type"><?php echo ucfirst($actividad['tipo']); ?></span>
                  <span class="communication-date">
                    <?php 
                    $fecha = $actividad['fecha_programada'] ?? $actividad['fecha_creacion'];
                    echo date('d/m/Y H:i', strtotime($fecha)); 
                    ?>
                  </span>
                </div>
                <div class="communication-description">
                  <?php echo htmlspecialchars($actividad['descripcion']); ?>
                </div>
                <div class="communication-footer">
                  <span class="communication-employee">
                    👤 <?php echo htmlspecialchars($actividad['empleado_nombre'] ?? 'Equipo Revive tu Hogar'); ?>
                  </span>
                  <span class="communication-status status-<?php echo htmlspecialchars($actividad['estado']); ?>">
                    <?php echo ucfirst($actividad['estado']); ?>
                  </span>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
        <div style="text-align: center; padding: 60px 20px; color: var(--marron-elegante);">
          <i class="fas fa-comments" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.5;"></i>
          <h3 style="color: var(--verde-oliva); margin-bottom: 10px;">Sin comunicaciones aún</h3>
          <p style="font-size: 1.1rem; margin: 0;">Aquí aparecerán todas las comunicaciones con nuestro equipo.</p>
        </div>
        <?php endif; ?>
      </div>

      <!-- Contenido de la pestaña Perfil -->
      <div id="tab-perfil" class="tab-content">
        <!-- Mi Perfil -->
        <section class="section-card">
          <h2 class="section-title">
            <i class="fas fa-user-edit"></i>
            Mi Perfil
          </h2>
          
          <div class="profile-form">
            <form method="post" action="/cliente/profile_update.php">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
              
              <div class="form-group">
                <label for="nombre">Nombre</label>
                <input id="nombre" class="form-input" type="text" name="nombre" value="<?php echo htmlspecialchars($cliente['nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
              </div>
              
              <div class="form-group">
                <label for="apellido">Apellido</label>
                <input id="apellido" class="form-input" type="text" name="apellido" value="<?php echo htmlspecialchars($cliente['apellido'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
              </div>
              
              <div class="form-group">
                <label for="correo">Correo</label>
                <input id="correo" class="form-input" type="email" value="<?php echo htmlspecialchars($cliente['correo'] ?? $userEmail, ENT_QUOTES, 'UTF-8'); ?>" readonly style="background: var(--beige-claro); cursor: not-allowed;" />
              </div>
              
              <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input id="telefono" class="form-input" type="text" name="telefono" value="<?php echo htmlspecialchars($cliente['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
              </div>
              
              <div class="form-group">
                <label for="direccion">Dirección</label>
                <input id="direccion" class="form-input" type="text" name="direccion" value="<?php echo htmlspecialchars($cliente['direccion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
              </div>
              
              <div class="form-actions">
                <button class="btn-modern primary" type="submit">
                  <i class="fas fa-save"></i> Guardar cambios
                </button>
                <a class="btn-modern secondary" href="/auth/logout.php">
                  <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                </a>
              </div>
            </form>
          </div>
        </section>

        <!-- Notificaciones en Perfil -->
        <?php if (!empty($notificaciones_cliente)): ?>
        <section class="section-card" id="notificaciones">
          <h2 class="section-title">
            <i class="fas fa-bell"></i>
            Notificaciones
          </h2>
          <div class="client-notifications-panel" style="margin-top: 12px;">
            <div class="notifications-header">
              <h3><i class="fas fa-envelope-open-text"></i> Últimas notificaciones</h3>
              <span class="notifications-badge"><?php echo count($notificaciones_cliente); ?></span>
            </div>
            <div class="notifications-grid">
              <?php foreach ($notificaciones_cliente as $notif): ?>
              <div class="client-notification-card notification-<?php echo htmlspecialchars($notif['tipo']); ?>">
                <div class="notification-icon"><?php echo $notif['icono']; ?></div>
                <div class="notification-content">
                  <div class="notification-title"><?php echo htmlspecialchars($notif['titulo']); ?></div>
                  <div class="notification-message"><?php echo htmlspecialchars($notif['mensaje']); ?></div>
                </div>
                <div class="notification-time"><?php echo htmlspecialchars($notif['tiempo']); ?></div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script>
    // Funcionalidad de pestañas
    document.addEventListener('DOMContentLoaded', function() {
      // Manejar clics en las pestañas
      const navTabs = document.querySelectorAll('.nav-tab');
      const tabContents = document.querySelectorAll('.tab-content');
      
      navTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
          e.preventDefault();
          
          // Remover clase active de todas las pestañas y contenidos
          navTabs.forEach(t => t.classList.remove('active'));
          tabContents.forEach(content => content.classList.remove('active'));
          
          // Agregar clase active a la pestaña clickeada
          this.classList.add('active');
          
          // Mostrar el contenido correspondiente
          const targetTab = this.getAttribute('data-tab');
          const targetContent = document.getElementById(targetTab);
          if (targetContent) {
            targetContent.classList.add('active');
          }
        });
      });
    });

    // Script de filtrado y paginación existente
    (function(){
      const list = document.getElementById('pedidosList');
      const q = document.getElementById('filtroBusqueda');
      const selEstado = document.getElementById('filtroEstado');
      const selPago = document.getElementById('filtroPago');
      const resumen = document.getElementById('filtroResumen');
      const pagNav = document.getElementById('paginacionControles');
      const pageInfo = document.getElementById('pageInfo');
      const pagePrev = document.getElementById('pagePrev');
      const pageNext = document.getElementById('pageNext');
      const pageSizeSel = document.getElementById('pageSize');
      if(!list || !q || !selEstado || !selPago || !resumen || !pagNav || !pageInfo || !pagePrev || !pageNext || !pageSizeSel) return;

      const toNorm = (s) => (s||'').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'');
      const getCards = () => Array.from(list.children).filter(el => el.classList.contains('pedido-card'));
      let filtered = [];
      let page = 1;

      function filterCards(){
        const s = toNorm(q.value.trim());
        const est = toNorm(selEstado.value);
        const pago = toNorm(selPago.value);
        const all = getCards();
        filtered = all.filter(card => {
          const id = (card.getAttribute('data-id')||'').toString();
          const plan = toNorm(card.getAttribute('data-plan')||'');
          const estado = toNorm(card.getAttribute('data-estado')||'');
          const pagoSt = toNorm(card.getAttribute('data-pago')||'');
          let ok = true;
          if (s) ok = plan.includes(s) || id.includes(s.replace('#',''));
          if (ok && est) {
            if (est === 'enviado') ok = estado.includes('enviad');
            else if (est === 'entregado') ok = estado.includes('entreg');
            else if (est === 'pendiente') ok = estado.includes('pend');
            else ok = estado === est;
          }
          if (ok && pago) ok = pagoSt === pago;
          return ok;
        });
        resumen.textContent = `Mostrando ${filtered.length} de ${getCards().length}`;
      }

      function renderPage(){
        const size = parseInt(pageSizeSel.value, 10) || 12;
        const total = filtered.length;
        const pages = Math.max(1, Math.ceil(total / size));
        if (page > pages) page = pages;
        const start = total ? (page - 1) * size + 1 : 0;
        const end = total ? Math.min(page * size, total) : 0;
        // Ocultar todo y mostrar solo el segmento actual
        getCards().forEach(el => { el.style.display = 'none'; });
        filtered.slice(start - 1, end).forEach(el => { el.style.display = ''; });
        // Actualizar controles
        pageInfo.textContent = `${start}-${end} de ${total}`;
        pagePrev.disabled = (page <= 1);
        pageNext.disabled = (page >= pages);
        pagNav.style.display = total > size ? 'flex' : 'none';
      }

      function apply(){
        filterCards();
        page = 1;
        renderPage();
      }

      ['input','change'].forEach(ev => {
        q.addEventListener(ev, apply);
        selEstado.addEventListener(ev, apply);
        selPago.addEventListener(ev, apply);
      });
      pagePrev.addEventListener('click', () => { if (page > 1) { page--; renderPage(); } });
      pageNext.addEventListener('click', () => { page++; renderPage(); });
      pageSizeSel.addEventListener('change', () => { page = 1; renderPage(); });

      apply();
    })();

    // Las funcionalidades CRM están en cliente-dashboard.js
    console.log('Dashboard de cliente cargado');
  </script>
</body>
</html>