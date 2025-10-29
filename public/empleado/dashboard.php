<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('empleado');
require_once __DIR__.'/../../app/config/db.php';

if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$token = $_SESSION['csrf'];

$flash_error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$flash_success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

$userId = (int)($_SESSION['user_id'] ?? 0);
$empleadoId = 0;

// Variables para notificaciones y actividades
$notificaciones = [];
$actividades_pendientes = [];
$actividades_hoy = [];
$tareas_urgentes = [];

if ($pdo instanceof PDO) {
    try {
        $se = $pdo->prepare('SELECT id FROM empleados WHERE usuario_id = ? LIMIT 1');
        $se->execute([$userId]);
        $empleadoId = (int)($se->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $empleadoId = 0;
    }
}

// Manejo de actualización de perfil (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'actualizar_perfil') {
    try {
        if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
            throw new RuntimeException('CSRF token inválido.');
        }
        if (!$empleadoId) {
            throw new RuntimeException('Empleado no encontrado.');
        }
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $telefono = trim((string)($_POST['telefono'] ?? ''));
        $correo = trim((string)($_POST['correo'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El correo no es válido.');
        }

        if ($pdo instanceof PDO) {
            $pdo->beginTransaction();
            // Obtener usuario_id del empleado
            $stmt0 = $pdo->prepare('SELECT usuario_id FROM empleados WHERE id = ? FOR UPDATE');
            $stmt0->execute([$empleadoId]);
            $usuario_id = (int)($stmt0->fetchColumn() ?: 0);
            if (!$usuario_id) {
                throw new RuntimeException('No se pudo resolver el usuario del empleado.');
            }
            // Actualiza datos en empleados
            $camposEmp = [];
            $valsEmp = [];
            if ($nombre !== '') { $camposEmp[] = 'nombre = ?'; $valsEmp[] = $nombre; }
            if ($telefono !== '') { $camposEmp[] = 'telefono = ?'; $valsEmp[] = $telefono; }
            if ($correo !== '') { $camposEmp[] = 'correo = ?'; $valsEmp[] = $correo; }
            if (!empty($camposEmp)) {
                $valsEmp[] = $empleadoId;
                $sqlEmp = 'UPDATE empleados SET '.implode(', ', $camposEmp).' WHERE id = ?';
                $pdo->prepare($sqlEmp)->execute($valsEmp);
            }
            // Actualiza correo en usuarios si cambió
            if ($correo !== '') {
                $pdo->prepare('UPDATE usuarios SET correo_electronico = ? WHERE id = ?')->execute([$correo, $usuario_id]);
            }
            // Cambio de contraseña opcional
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('UPDATE usuarios SET contrasena_hash = ? WHERE id = ?')->execute([$hash, $usuario_id]);
            }
            $pdo->commit();
        }
        $_SESSION['flash_success'] = 'Perfil actualizado correctamente.';
    } catch (Throwable $e) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
        $_SESSION['flash_error'] = 'No se pudo actualizar el perfil: '. $e->getMessage();
    }
    header('Location: /empleado/dashboard.php#perfil');
    exit;
}

// Función para generar código de factura determinístico
function generar_codigo_factura($facturaId, $fechaFactura) {
    if (!$facturaId || !$fechaFactura) return null;
    $salt = 'RTH-SALT-2024';
    $base = $facturaId.'|'.substr((string)$fechaFactura, 0, 10).'|'.$salt;
    return strtoupper(substr(hash('sha256', $base), 0, 8));
}

$pedidos = [];
$pagos_exitosos = 0;
$asignados_a_mi = 0;
$total_pedidos = 0;
$cont_pendientes = 0;
$cont_enviados = 0;
$cont_completados = 0;

// Datos de perfil para el formulario
$perfil = [
  'nombre' => '',
  'telefono' => '',
  'correo' => '',
  'nombre_usuario' => ''
];

if ($pdo instanceof PDO) {
    try {
        // Pedidos asignados a este empleado o sin asignar aún (incluye p.empleado_id)
        $q = $pdo->prepare('SELECT p.id, p.fecha, p.total, p.estado, p.empleado_id, c.nombre AS cliente_nombre, pl.nombre_plan, f.id AS factura_id, f.estado_pago, f.fecha_factura FROM pedidos p LEFT JOIN clientes c ON c.id=p.cliente_id LEFT JOIN planes pl ON pl.id=p.plan_id LEFT JOIN facturas f ON f.pedido_id=p.id WHERE (p.empleado_id = ? OR p.empleado_id IS NULL) ORDER BY p.fecha DESC');
        $q->execute([$empleadoId]);
        $pedidos = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $total_pedidos = count($pedidos);
        $asignados_a_mi = 0;
        $cont_pendientes = 0; $cont_enviados = 0; $cont_completados = 0;
        foreach ($pedidos as $p) { 
            if ((int)($p['empleado_id'] ?? 0) === $empleadoId) { $asignados_a_mi++; }
            $estado = strtolower((string)($p['estado'] ?? ''));
            if ($estado === 'pendiente') { $cont_pendientes++; }
            elseif ($estado === 'enviado') { $cont_enviados++; }
            elseif ($estado === 'completado') { $cont_completados++; }
        }

        // Cargar historial de estados para los pedidos visibles
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
            // No bloquear el dashboard si falla la creación; se continuará sin historial
        }
        $historialPorPedido = [];
        if (!empty($pedidos)) {
            $ids = array_map('intval', array_column($pedidos, 'id'));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sqlH = 'SELECT h.pedido_id, h.estado_anterior, h.estado_nuevo, h.motivo, h.created_at, e.nombre AS empleado_nombre
                         FROM historial_estados h
                         LEFT JOIN empleados e ON e.id = h.empleado_id
                         WHERE h.pedido_id IN (' . $placeholders . ')
                         ORDER BY h.created_at DESC, h.id DESC';
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
        }
        // Contador de pagos exitosos del empleado
        if ($empleadoId) {
            $c = $pdo->prepare('SELECT COALESCE(SUM(f.estado_pago="pagado"),0) FROM pedidos p LEFT JOIN facturas f ON f.pedido_id=p.id WHERE p.empleado_id=?');
            $c->execute([$empleadoId]);
            $pagos_exitosos = (int)($c->fetchColumn() ?: 0);
        }

        // Cargar datos de perfil
        if ($empleadoId) {
            $sp = $pdo->prepare('SELECT e.nombre, e.telefono, e.correo, u.nombre_usuario, u.correo_electronico FROM empleados e JOIN usuarios u ON u.id = e.usuario_id WHERE e.id = ? LIMIT 1');
            $sp->execute([$empleadoId]);
            $perfil_db = $sp->fetch(PDO::FETCH_ASSOC) ?: [];
            if ($perfil_db) {
                $perfil['nombre'] = (string)($perfil_db['nombre'] ?? '');
                $perfil['telefono'] = (string)($perfil_db['telefono'] ?? '');
                $perfil['correo'] = (string)($perfil_db['correo'] ?: ($perfil_db['correo_electronico'] ?? ''));
                $perfil['nombre_usuario'] = (string)($perfil_db['nombre_usuario'] ?? '');
            }
        }

        // Cargar actividades y notificaciones del empleado
        if ($empleadoId) {
            // Crear tabla de actividades si no existe
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
            } catch (Throwable $e) {
                // Continuar sin actividades si falla la creación
            }

            // Actividades pendientes del empleado
            try {
                $act_pendientes = $pdo->prepare('SELECT a.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido FROM actividades a LEFT JOIN clientes c ON c.id = a.cliente_id WHERE a.empleado_id = ? AND a.estado = "pendiente" ORDER BY a.prioridad DESC, a.fecha_programada ASC, a.fecha_creacion ASC LIMIT 10');
                $act_pendientes->execute([$empleadoId]);
                $actividades_pendientes = $act_pendientes->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $actividades_pendientes = [];
            }

            // Actividades de hoy
            try {
                $act_hoy = $pdo->prepare('SELECT a.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido FROM actividades a LEFT JOIN clientes c ON c.id = a.cliente_id WHERE a.empleado_id = ? AND DATE(a.fecha_programada) = CURDATE() ORDER BY a.fecha_programada ASC');
                $act_hoy->execute([$empleadoId]);
                $actividades_hoy = $act_hoy->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $actividades_hoy = [];
            }

            // Tareas urgentes (prioridad alta y vencidas)
            try {
                $tareas_urg = $pdo->prepare('SELECT a.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido FROM actividades a LEFT JOIN clientes c ON c.id = a.cliente_id WHERE a.empleado_id = ? AND a.estado = "pendiente" AND (a.prioridad = "alta" OR a.fecha_programada < NOW()) ORDER BY a.fecha_programada ASC LIMIT 5');
                $tareas_urg->execute([$empleadoId]);
                $tareas_urgentes = $tareas_urg->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable $e) {
                $tareas_urgentes = [];
            }

            // Insertar algunas actividades de ejemplo si no existen
            try {
                $count_actividades = $pdo->prepare('SELECT COUNT(*) FROM actividades WHERE empleado_id = ?');
                $count_actividades->execute([$empleadoId]);
                $total_actividades = (int)$count_actividades->fetchColumn();
                
                if ($total_actividades === 0 && $empleadoId > 0) {
                    // Obtener algunos clientes para las actividades de ejemplo
                    $clientes_ejemplo = $pdo->query('SELECT id, nombre FROM clientes LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($clientes_ejemplo)) {
                        $actividades_ejemplo = [
                            [
                                'tipo' => 'llamada',
                                'descripcion' => 'Llamar para confirmar detalles del proyecto de remodelación',
                                'cliente_id' => $clientes_ejemplo[0]['id'],
                                'empleado_id' => $empleadoId,
                                'fecha_programada' => date('Y-m-d H:i:s', strtotime('+2 hours')),
                                'prioridad' => 'alta'
                            ],
                            [
                                'tipo' => 'reunion',
                                'descripcion' => 'Reunión para revisar avances del proyecto',
                                'cliente_id' => $clientes_ejemplo[1]['id'] ?? $clientes_ejemplo[0]['id'],
                                'empleado_id' => $empleadoId,
                                'fecha_programada' => date('Y-m-d H:i:s', strtotime('today 14:00')),
                                'prioridad' => 'media'
                            ],
                            [
                                'tipo' => 'seguimiento',
                                'descripcion' => 'Seguimiento post-entrega del proyecto',
                                'cliente_id' => $clientes_ejemplo[2]['id'] ?? $clientes_ejemplo[0]['id'],
                                'empleado_id' => $empleadoId,
                                'fecha_programada' => date('Y-m-d H:i:s', strtotime('tomorrow 10:00')),
                                'prioridad' => 'baja'
                            ]
                        ];
                        
                        foreach ($actividades_ejemplo as $actividad) {
                            $stmt_insert = $pdo->prepare('INSERT INTO actividades (tipo, descripcion, cliente_id, empleado_id, fecha_programada, prioridad) VALUES (?, ?, ?, ?, ?, ?)');
                            $stmt_insert->execute([
                                $actividad['tipo'],
                                $actividad['descripcion'],
                                $actividad['cliente_id'],
                                $actividad['empleado_id'],
                                $actividad['fecha_programada'],
                                $actividad['prioridad']
                            ]);
                        }
                        
                        // Recargar actividades después de insertar ejemplos
                        $act_pendientes->execute([$empleadoId]);
                        $actividades_pendientes = $act_pendientes->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        
                        $act_hoy->execute([$empleadoId]);
                        $actividades_hoy = $act_hoy->fetchAll(PDO::FETCH_ASSOC) ?: [];
                        
                        $tareas_urg->execute([$empleadoId]);
                        $tareas_urgentes = $tareas_urg->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    }
                }
            } catch (Throwable $e) {
                // Continuar sin actividades de ejemplo
            }

            // Generar notificaciones (siempre mostrar al menos una)
            $notificaciones = [];
            
            // Notificación de bienvenida si es la primera vez
            $notificaciones[] = [
                'tipo' => 'success',
                'icono' => '👋',
                'titulo' => '¡Bienvenido!',
                'mensaje' => 'Dashboard mejorado con notificaciones y seguimiento de actividades',
                'accion' => '#estadisticas',
                'tiempo' => 'Nuevo'
            ];
            
            // Notificación de pedidos sin asignar
            $pedidos_sin_asignar = array_filter($pedidos, function($p) { return empty($p['empleado_id']); });
            if (count($pedidos_sin_asignar) > 0) {
                $notificaciones[] = [
                    'tipo' => 'info',
                    'icono' => '📦',
                    'titulo' => 'Pedidos disponibles',
                    'mensaje' => count($pedidos_sin_asignar) . ' pedidos esperan ser asignados',
                    'accion' => '#pedidos',
                    'tiempo' => 'Ahora'
                ];
            }

            // Notificación de tareas urgentes
            if (count($tareas_urgentes) > 0) {
                $notificaciones[] = [
                    'tipo' => 'warning',
                    'icono' => '⚠️',
                    'titulo' => 'Tareas urgentes',
                    'mensaje' => count($tareas_urgentes) . ' tareas requieren atención inmediata',
                    'accion' => '#actividades',
                    'tiempo' => 'Urgente'
                ];
            }

            // Notificación de actividades de hoy
            if (count($actividades_hoy) > 0) {
                $notificaciones[] = [
                    'tipo' => 'success',
                    'icono' => '📅',
                    'titulo' => 'Agenda de hoy',
                    'mensaje' => count($actividades_hoy) . ' actividades programadas para hoy',
                    'accion' => '#agenda',
                    'tiempo' => 'Hoy'
                ];
            }

            // Notificación de pagos pendientes
            $facturas_pendientes = array_filter($pedidos, function($p) use ($empleadoId) { 
                return (int)($p['empleado_id'] ?? 0) === $empleadoId && ($p['estado_pago'] ?? '') === 'pendiente'; 
            });
            if (count($facturas_pendientes) > 0) {
                $notificaciones[] = [
                    'tipo' => 'error',
                    'icono' => '💰',
                    'titulo' => 'Pagos pendientes',
                    'mensaje' => count($facturas_pendientes) . ' facturas esperan confirmación de pago',
                    'accion' => '#facturas',
                    'tiempo' => 'Pendiente'
                ];
            }

            // Notificación de rendimiento si tiene pedidos asignados
             if ($asignados_a_mi > 0) {
                 $notificaciones[] = [
                     'tipo' => 'info',
                     'icono' => '📊',
                     'titulo' => 'Tu rendimiento',
                     'mensaje' => "Tienes {$asignados_a_mi} pedidos asignados y {$pagos_exitosos} pagos confirmados",
                     'accion' => '#estadisticas',
                     'tiempo' => 'Resumen'
                 ];
             }

             // Notificaciones adicionales para demostración
             $notificaciones[] = [
                 'tipo' => 'warning',
                 'icono' => '🕐',
                 'titulo' => 'Recordatorio',
                 'mensaje' => 'Revisar pedidos pendientes de esta semana',
                 'accion' => '#pedidos',
                 'tiempo' => '1h'
             ];

             if (count($actividades_pendientes) === 0) {
                 // Si no hay actividades, crear algunas de ejemplo para mostrar
                 $actividades_pendientes = [
                     [
                         'id' => 'demo1',
                         'tipo' => 'llamada',
                         'descripcion' => 'Contactar cliente para confirmar cita',
                         'cliente_nombre' => 'Cliente',
                         'cliente_apellido' => 'Ejemplo',
                         'prioridad' => 'alta',
                         'fecha_programada' => date('Y-m-d H:i:s', strtotime('+1 hour'))
                     ],
                     [
                         'id' => 'demo2',
                         'tipo' => 'tarea',
                         'descripcion' => 'Preparar materiales para proyecto',
                         'cliente_nombre' => 'Otro',
                         'cliente_apellido' => 'Cliente',
                         'prioridad' => 'media',
                         'fecha_programada' => date('Y-m-d H:i:s', strtotime('today 15:00'))
                     ]
                 ];
                 $tareas_urgentes = [$actividades_pendientes[0]]; // Primera tarea como urgente
             }

             if (count($actividades_hoy) === 0) {
                 // Si no hay actividades de hoy, crear algunas de ejemplo
                 $actividades_hoy = [
                     [
                         'id' => 'hoy1',
                         'tipo' => 'reunion',
                         'descripcion' => 'Reunión de seguimiento de proyecto',
                         'cliente_nombre' => 'Cliente',
                         'cliente_apellido' => 'Importante',
                         'fecha_programada' => date('Y-m-d H:i:s', strtotime('today 14:00'))
                     ],
                     [
                         'id' => 'hoy2',
                         'tipo' => 'llamada',
                         'descripcion' => 'Llamada de confirmación de entrega',
                         'cliente_nombre' => 'Otro',
                         'cliente_apellido' => 'Cliente',
                         'fecha_programada' => date('Y-m-d H:i:s', strtotime('today 16:30'))
                     ]
                 ];
             }
        }
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'No se pudieron cargar los datos. Inténtalo más tarde.';
    }
}

// Unificado: evitar duplicación de notificaciones
// Las notificaciones se generan dentro del bloque principal de carga de datos.

?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Empleado - Revive tu Hogar</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="../assets/js/empleado-dashboard.js" defer></script>
  <style>
    /* Dashboard moderno para empleados */
    :root {
      --dashboard-bg: linear-gradient(135deg, #FAF9F6 0%, #F5F5DC 100%);
      --card-shadow: 0 4px 20px rgba(140, 106, 79, 0.08);
      --card-shadow-hover: 0 8px 30px rgba(140, 106, 79, 0.15);
      --gradient-primary: linear-gradient(135deg, #D2B48C 0%, #8C6A4F 100%);
      --glass-bg: rgba(250, 249, 246, 0.85);
    }
    
    body {
      background: var(--dashboard-bg);
      min-height: 100vh;
    }
    
    .dashboard-container {
      max-width: 1400px;
      margin: 0 auto;
      padding: 20px;
    }
    
    /* Header moderno */
    .dashboard-header {
      background: var(--glass-bg);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 20px;
      padding: 24px 32px;
      margin-bottom: 32px;
      box-shadow: var(--card-shadow);
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 20px;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }
    
    .user-avatar {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: var(--gradient-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 24px;
      font-weight: 600;
      box-shadow: 0 4px 15px rgba(107, 112, 92, 0.3);
    }
    
    .header-info h1 {
      margin: 0;
      font-size: 28px;
      font-weight: 700;
      color: var(--gris);
      letter-spacing: -0.02em;
    }
    
    .header-info p {
      margin: 4px 0 0;
      color: #666;
      font-size: 16px;
    }
    
    .header-actions {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    
    .btn-modern {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 20px;
      border-radius: 12px;
      text-decoration: none;
      font-weight: 500;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: none;
      cursor: pointer;
      font-size: 14px;
    }
    
    .btn-primary {
      background: var(--gradient-primary);
      color: white;
      box-shadow: 0 4px 15px rgba(107, 112, 92, 0.3);
    }
    
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(107, 112, 92, 0.4);
    }
    
    .btn-secondary {
      background: rgba(255, 255, 255, 0.8);
      color: var(--gris);
      border: 1px solid rgba(107, 112, 92, 0.2);
    }
    
    .btn-secondary:hover {
      background: white;
      transform: translateY(-1px);
    }
    
    /* Grid de estadísticas */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }
    
    .stat-card {
      background: var(--glass-bg);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 16px;
      padding: 24px;
      text-align: center;
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
      background: var(--gradient-primary);
    }
    
    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--card-shadow-hover);
    }
    
    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: var(--gradient-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
      color: white;
      font-size: 20px;
    }
    
    .stat-number {
      font-size: 32px;
      font-weight: 700;
      color: var(--gris);
      margin: 0;
      letter-spacing: -0.02em;
    }
    
    .stat-label {
      color: #666;
      font-size: 14px;
      margin: 8px 0 0;
      font-weight: 500;
    }
    
    /* Contenido principal */
    .main-content {
      display: flex;
      flex-direction: column;
      gap: 32px;
    }
    
    .content-card {
      background: var(--glass-bg);
      backdrop-filter: blur(20px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 20px;
      padding: 32px;
      box-shadow: var(--card-shadow);
      transition: all 0.3s ease;
    }
    
    .content-card:hover {
      box-shadow: var(--card-shadow-hover);
    }
    
    .card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 1px solid rgba(107, 112, 92, 0.1);
    }
    
    .card-title {
      font-size: 24px;
      font-weight: 700;
      color: var(--gris);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    
    .card-title i {
      color: var(--verde);
    }
    
    /* Filtros modernos */
    .filters-container {
      display: flex;
      gap: 16px;
      margin-bottom: 24px;
      flex-wrap: wrap;
    }
    
    .filter-input {
      flex: 1;
      min-width: 200px;
      padding: 12px 16px;
      border: 1px solid rgba(107, 112, 92, 0.2);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.8);
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .filter-input:focus {
      outline: none;
      border-color: var(--verde);
      box-shadow: 0 0 0 3px rgba(107, 112, 92, 0.1);
      background: white;
    }
    
    /* Tabla moderna */
    .modern-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      background: transparent;
    }
    
    .modern-table thead th {
      background: rgba(107, 112, 92, 0.05);
      padding: 16px;
      text-align: left;
      font-weight: 600;
      color: var(--gris);
      border: none;
      font-size: 14px;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    
    .modern-table thead th:first-child {
      border-radius: 12px 0 0 12px;
    }
    
    .modern-table thead th:last-child {
      border-radius: 0 12px 12px 0;
    }
    
    .modern-table tbody tr {
      transition: all 0.3s ease;
    }
    
    .modern-table tbody tr:hover {
      background: rgba(107, 112, 92, 0.03);
      transform: scale(1.01);
    }
    
    .modern-table tbody td {
      padding: 16px;
      border-bottom: 1px solid rgba(107, 112, 92, 0.08);
      vertical-align: middle;
    }
    
    /* Badges modernos */
    .badge-modern {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .badge-success {
      background: rgba(34, 197, 94, 0.1);
      color: #16a34a;
      border: 1px solid rgba(34, 197, 94, 0.2);
    }
    
    .badge-warning {
      background: rgba(245, 158, 11, 0.1);
      color: #d97706;
      border: 1px solid rgba(245, 158, 11, 0.2);
    }
    
    .badge-info {
      background: rgba(59, 130, 246, 0.1);
      color: #2563eb;
      border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .badge-neutral {
      background: rgba(107, 112, 92, 0.1);
      color: var(--verde);
      border: 1px solid rgba(107, 112, 92, 0.2);
    }
    
    /* Botones de acción */
    .action-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    .btn-action {
      padding: 8px 16px;
      border-radius: 8px;
      border: none;
      font-size: 12px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    
    .btn-action.success {
      background: #22c55e;
      color: white;
    }
    
    .btn-action.danger {
      background: #ef4444;
      color: white;
    }
    
    .btn-action.primary {
      background: var(--verde);
      color: white;
    }
    
    .btn-action:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    /* Historial desplegable */
    .history-dropdown {
      position: relative;
    }
    
    .history-dropdown summary {
      cursor: pointer;
      padding: 8px 12px;
      background: rgba(107, 112, 92, 0.1);
      border-radius: 8px;
      border: none;
      color: var(--verde);
      font-weight: 500;
      list-style: none;
      transition: all 0.3s ease;
    }
    
    .history-dropdown summary:hover {
      background: rgba(107, 112, 92, 0.2);
    }
    
    .history-dropdown[open] summary {
      background: var(--verde);
      color: white;
    }
    
    .history-content {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid rgba(107, 112, 92, 0.2);
      border-radius: 12px;
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.15);
      z-index: 100;
      margin-top: 8px;
      padding: 16px;
      min-width: 300px;
    }
    
    .history-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      border-radius: 8px;
      background: rgba(107, 112, 92, 0.03);
      margin-bottom: 8px;
    }
    
    .history-item:last-child {
      margin-bottom: 0;
    }
    
    /* Formulario de perfil */
    .profile-form {
      display: grid;
      gap: 20px;
    }
    
    .form-group {
      display: grid;
      gap: 8px;
    }
    
    .form-label {
      font-weight: 600;
      color: var(--gris);
      font-size: 14px;
    }
    
    .form-input {
      padding: 12px 16px;
      border: 1px solid rgba(107, 112, 92, 0.2);
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.8);
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .form-input:focus {
      outline: none;
      border-color: var(--verde);
      box-shadow: 0 0 0 3px rgba(107, 112, 92, 0.1);
      background: white;
    }
    
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
    }
    
    /* Alertas modernas */
    .alert-modern {
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
    }
    
    .alert-success {
      background: rgba(34, 197, 94, 0.1);
      color: #16a34a;
      border: 1px solid rgba(34, 197, 94, 0.2);
    }
    
    .alert-error {
      background: rgba(239, 68, 68, 0.1);
      color: #dc2626;
      border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    /* Estilos para notificaciones */
    .notifications-panel {
      background: linear-gradient(135deg, #fff 0%, #f8fffe 100%);
      border: 2px solid var(--verde);
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 32px;
      box-shadow: 0 8px 30px rgba(107, 112, 92, 0.15);
      position: relative;
      overflow: hidden;
    }

    .notifications-panel::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--verde), var(--beige-claro));
    }

    .notifications-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid rgba(107, 112, 92, 0.1);
    }

    .notifications-header h3 {
      margin: 0;
      color: var(--gris);
      font-size: 20px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .notifications-count {
      background: var(--gradient-primary);
      color: white;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    .notifications-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 16px;
    }

    .notification-card {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 16px;
      border-radius: 12px;
      border: 1px solid;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .notification-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .notification-info {
      background: rgba(59, 130, 246, 0.1);
      border-color: rgba(59, 130, 246, 0.2);
    }

    .notification-warning {
      background: rgba(245, 158, 11, 0.1);
      border-color: rgba(245, 158, 11, 0.2);
    }

    .notification-success {
      background: rgba(34, 197, 94, 0.1);
      border-color: rgba(34, 197, 94, 0.2);
    }

    .notification-error {
      background: rgba(239, 68, 68, 0.1);
      border-color: rgba(239, 68, 68, 0.2);
    }

    .notification-icon {
      font-size: 24px;
      flex-shrink: 0;
    }

    .notification-content {
      flex: 1;
    }

    .notification-title {
      font-weight: 600;
      color: var(--gris);
      margin-bottom: 4px;
    }

    .notification-message {
      font-size: 14px;
      color: #666;
    }

    .notification-time {
      font-size: 12px;
      color: #999;
      font-weight: 500;
    }

    /* Estilos para actividades */
    .activities-panel {
      background: linear-gradient(135deg, #fff 0%, #f8fffe 100%);
      border: 2px solid var(--beige-claro);
      border-radius: 20px;
      padding: 24px;
      margin-bottom: 32px;
      box-shadow: 0 8px 30px rgba(139, 115, 85, 0.15);
      position: relative;
      overflow: hidden;
    }

    .activities-panel::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--beige-claro), var(--verde));
    }

    .activities-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 24px;
      padding-bottom: 16px;
      border-bottom: 1px solid rgba(107, 112, 92, 0.1);
    }

    .activities-header h3 {
      margin: 0;
      color: var(--gris);
      font-size: 20px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .activities-summary {
      display: flex;
      gap: 16px;
    }

    .activity-count, .urgent-count {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    .activity-count {
      background: rgba(34, 197, 94, 0.1);
      color: #16a34a;
    }

    .urgent-count {
      background: rgba(239, 68, 68, 0.1);
      color: #dc2626;
    }

    .activities-content {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 24px;
    }

    .urgent-tasks h4, .today-activities h4 {
      margin: 0 0 16px 0;
      color: var(--gris);
      font-size: 16px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .tasks-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .task-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px;
      border-radius: 12px;
      border: 1px solid rgba(239, 68, 68, 0.2);
      background: rgba(239, 68, 68, 0.05);
      transition: all 0.3s ease;
    }

    .task-item:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(239, 68, 68, 0.1);
    }

    .task-icon {
      font-size: 20px;
      flex-shrink: 0;
    }

    .task-content {
      flex: 1;
    }

    .task-title {
      font-weight: 600;
      color: var(--gris);
      margin-bottom: 4px;
    }

    .task-client {
      font-size: 14px;
      color: #666;
      margin-bottom: 4px;
    }

    .task-time {
      font-size: 12px;
      color: #999;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .task-priority {
      padding: 4px 8px;
      border-radius: 8px;
      font-size: 10px;
      font-weight: 700;
      text-align: center;
    }

    .priority-alta {
      background: #dc2626;
      color: white;
    }

    .priority-media {
      background: #D2B48C;
      color: white;
    }

    .priority-baja {
      background: #16a34a;
      color: white;
    }

    /* Timeline de actividades */
    .activities-timeline {
      position: relative;
      padding-left: 24px;
    }

    .activities-timeline::before {
      content: '';
      position: absolute;
      left: 8px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: linear-gradient(180deg, var(--verde), var(--beige-claro));
    }

    .timeline-item {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 20px;
      position: relative;
    }

    .timeline-time {
      font-size: 12px;
      font-weight: 600;
      color: var(--verde);
      min-width: 40px;
      text-align: right;
      position: absolute;
      left: -60px;
    }

    .timeline-marker {
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: var(--verde);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 8px;
      color: white;
      position: absolute;
      left: -24px;
      z-index: 2;
    }

    .timeline-content {
      background: rgba(255, 255, 255, 0.8);
      padding: 12px 16px;
      border-radius: 12px;
      border: 1px solid rgba(107, 112, 92, 0.1);
      flex: 1;
    }

    .timeline-title {
      font-weight: 600;
      color: var(--gris);
      margin-bottom: 4px;
    }

    .timeline-client {
      font-size: 14px;
      color: #666;
    }

    /* Estilos para botones de acción de tareas */
    .task-actions {
      display: flex;
      gap: 8px;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .task-item:hover .task-actions {
      opacity: 1;
    }

    .task-btn {
      width: 32px;
      height: 32px;
      border: none;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 12px;
    }

    .complete-btn {
      background: linear-gradient(135deg, #10b981, #34d399);
      color: white;
    }

    .complete-btn:hover {
      background: linear-gradient(135deg, #059669, #10b981);
      transform: scale(1.1);
    }

    .postpone-btn {
      background: linear-gradient(135deg, #D2B48C, #F5DEB3);
      color: white;
    }

    .postpone-btn:hover {
      background: linear-gradient(135deg, #8C6A4F, #D2B48C);
      transform: scale(1.1);
    }

    /* Estilos para tooltips */
    .tooltip {
      position: absolute;
      background: rgba(0, 0, 0, 0.8);
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 12px;
      z-index: 10000;
      pointer-events: none;
      animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(4px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Estilos para toasts */
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 16px 20px;
      border-radius: 12px;
      color: white;
      font-weight: 600;
      z-index: 9999;
      transform: translateX(100%);
      transition: all 0.3s ease;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      display: flex;
      align-items: center;
      gap: 12px;
      max-width: 400px;
    }

    .toast-close {
      background: none;
      border: none;
      color: white;
      cursor: pointer;
      padding: 4px;
      border-radius: 4px;
      transition: background 0.3s ease;
    }

    .toast-close:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    /* Efectos de hover mejorados */
    .stat-card, .notification-card, .task-item {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stat-card:hover, .notification-card:hover {
      transform: translateY(-4px) scale(1.02);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }

    /* Animaciones de carga */
    .fade-in {
      opacity: 0;
      transform: translateY(20px);
      transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .fade-in.loaded {
      opacity: 1;
      transform: translateY(0);
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .main-content {
        flex-direction: column;
      }
      
      .notifications-grid {
        grid-template-columns: 1fr;
      }
      
      .activities-content {
        grid-template-columns: 1fr;
      }
      
      .timeline-time {
        position: static;
        min-width: auto;
        text-align: left;
        margin-bottom: 8px;
      }
      
      .dashboard-header {
        padding: 20px;
        text-align: center;
      }
      
      .header-left {
        flex-direction: column;
        text-align: center;
      }
    }

    /* Estilos para las pestañas del empleado */
    .employee-nav {
      display: flex;
      background: white;
      border-radius: 12px;
      padding: 8px;
      margin-bottom: 24px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      gap: 4px;
      overflow-x: auto;
    }

    .employee-nav .nav-tab {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 12px 20px;
      border-radius: 8px;
      text-decoration: none;
      color: #64748b;
      font-weight: 500;
      transition: all 0.3s ease;
      white-space: nowrap;
      position: relative;
      min-width: fit-content;
    }

    .employee-nav .nav-tab:hover {
      background: #f1f5f9;
      color: #475569;
      transform: translateY(-1px);
    }

    .employee-nav .nav-tab.active {
      background: linear-gradient(135deg, #D2B48C 0%, #8C6A4F 100%);
      color: white;
      box-shadow: 0 4px 12px rgba(210, 180, 140, 0.4);
    }

    .employee-nav .nav-tab.active::before {
      content: '';
      position: absolute;
      bottom: -8px;
      left: 50%;
      transform: translateX(-50%);
      width: 0;
      height: 0;
      border-left: 6px solid transparent;
      border-right: 6px solid transparent;
      border-top: 6px solid #D2B48C;
    }

    .employee-nav .nav-tab i {
      font-size: 16px;
    }

    .employee-nav .nav-tab span {
      font-size: 14px;
    }

    /* Contenido de las pestañas */
    .tab-content {
      display: none;
      animation: fadeIn 0.3s ease-in-out;
    }

    .tab-content.active {
      display: block;
    }

    /* Animación de entrada */
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Estilos para el dashboard simplificado */
    .dashboard-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .summary-card {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
      display: flex;
      align-items: center;
      gap: 15px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .summary-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .summary-icon {
      width: 50px;
      height: 50px;
      border-radius: 12px;
      background: linear-gradient(135deg, #D2B48C 0%, #8C6A4F 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
    }

    .summary-content h3 {
      margin: 0 0 8px 0;
      color: #2d3748;
      font-size: 16px;
      font-weight: 600;
    }

    .summary-stats {
      display: flex;
      gap: 15px;
    }

    .stat-item {
      font-size: 14px;
      color: #718096;
    }

    .stat-item strong {
      color: #2d3748;
      font-size: 18px;
    }

    .stat-item.urgent strong {
      color: #e53e3e;
    }

    /* Sección de tareas urgentes compacta */
    .urgent-section {
      background: white;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 30px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 1px solid #e2e8f0;
    }

    .section-header h3 {
      margin: 0;
      color: #2d3748;
      font-size: 18px;
      font-weight: 600;
    }

    .view-all-link a {
      color: #8C6A4F;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
    }

    .view-all-link a:hover {
      text-decoration: underline;
    }

    .urgent-tasks-compact {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .task-compact {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px;
      background: #f7fafc;
      border-radius: 8px;
      border-left: 4px solid #e53e3e;
      transition: all 0.3s ease;
    }

    .task-compact:hover {
      background: #edf2f7;
      transform: translateX(5px);
    }

    .task-compact .task-icon {
      font-size: 20px;
      width: 35px;
      text-align: center;
    }

    .task-info {
      flex: 1;
    }

    .task-info .task-title {
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 4px;
    }

    .task-info .task-meta {
      font-size: 13px;
      color: #718096;
    }

    .task-priority {
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .priority-alta {
      background: #fed7d7;
      color: #c53030;
    }

    .priority-media {
      background: #feebc8;
      color: #dd6b20;
    }

    .priority-baja {
      background: #c6f6d5;
      color: #38a169;
    }

    .more-tasks {
      text-align: center;
      padding: 10px;
    }

    .more-tasks a {
      color: #8C6A4F;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
    }

    /* Estadísticas compactas */
    .stats-grid-compact {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
    }

    .stat-card-compact {
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
      display: flex;
      align-items: center;
      gap: 15px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stat-card-compact:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
    }

    .stat-card-compact .stat-icon {
      width: 45px;
      height: 45px;
      border-radius: 10px;
      background: linear-gradient(135deg, #D2B48C 0%, #8C6A4F 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 18px;
    }

    .stat-content .stat-number {
      font-size: 24px;
      font-weight: 700;
      color: #2d3748;
      margin-bottom: 4px;
    }

    .stat-content .stat-label {
      font-size: 13px;
      color: #718096;
      font-weight: 500;
    }
    
    @media (max-width: 768px) {
      .dashboard-container {
        padding: 16px;
      }
      
      .stats-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
      }
      
      .content-card {
        padding: 20px;
      }
      
      .filters-container {
        flex-direction: column;
      }
      
      .filter-input {
        min-width: auto;
      }
      
      .form-row {
        grid-template-columns: 1fr;
      }
      
      .action-buttons {
        flex-direction: column;
      }
      
      .modern-table {
        font-size: 12px;
      }
      
      .modern-table thead th,
      .modern-table tbody td {
        padding: 12px 8px;
      }

      /* Responsividad para las pestañas */
      .employee-nav {
        padding: 6px;
        margin-bottom: 16px;
      }

      .employee-nav .nav-tab {
        padding: 10px 16px;
        font-size: 13px;
      }

      .employee-nav .nav-tab i {
        font-size: 14px;
      }

      .employee-nav .nav-tab span {
        display: none;
      }

      /* Responsividad para dashboard simplificado */
      .dashboard-summary {
        grid-template-columns: 1fr;
        gap: 15px;
        margin-bottom: 20px;
      }

      .summary-card {
        padding: 15px;
        gap: 12px;
      }

      .summary-icon {
        width: 40px;
        height: 40px;
        font-size: 16px;
      }

      .summary-content h3 {
        font-size: 14px;
      }

      .summary-stats {
        gap: 10px;
      }

      .stat-item {
        font-size: 12px;
      }

      .stat-item strong {
        font-size: 16px;
      }

      .urgent-section {
        padding: 15px;
        margin-bottom: 20px;
      }

      .section-header h3 {
        font-size: 16px;
      }

      .task-compact {
        padding: 12px;
        gap: 12px;
      }

      .task-compact .task-icon {
        font-size: 16px;
        width: 30px;
      }

      .task-info .task-title {
        font-size: 14px;
      }

      .task-info .task-meta {
        font-size: 12px;
      }

      .stats-grid-compact {
        grid-template-columns: 1fr;
        gap: 15px;
      }

      .stat-card-compact {
        padding: 15px;
        gap: 12px;
      }

      .stat-card-compact .stat-icon {
        width: 35px;
        height: 35px;
        font-size: 14px;
      }

      .stat-content .stat-number {
        font-size: 20px;
      }

      .stat-content .stat-label {
        font-size: 12px;
      }
    }

    @media (max-width: 480px) {
      .employee-nav {
        justify-content: space-between;
      }

      .employee-nav .nav-tab {
        flex: 1;
        justify-content: center;
        padding: 8px 12px;
      }
    }
    
    /* Animaciones */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .fade-in {
      animation: fadeInUp 0.6s ease-out;
    }
    
    .stagger-1 { animation-delay: 0.1s; }
    .stagger-2 { animation-delay: 0.2s; }
    .stagger-3 { animation-delay: 0.3s; }
    .stagger-4 { animation-delay: 0.4s; }
    .stagger-5 { animation-delay: 0.5s; }
    .stagger-6 { animation-delay: 0.6s; }
    
    /* Estilos del Modal de Confirmación de Pago */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      display: flex;
      justify-content: center;
      align-items: center;
      z-index: 1000;
      backdrop-filter: blur(5px);
    }

    .modal-content {
      background: white;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      max-width: 600px;
      width: 90%;
      max-height: 90vh;
      overflow-y: auto;
      animation: modalSlideIn 0.3s ease-out;
    }

    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-30px) scale(0.95);
      }
      to {
        opacity: 1;
        transform: translateY(0) scale(1);
      }
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 24px;
      border-bottom: 1px solid rgba(107, 112, 92, 0.2);
      background: linear-gradient(135deg, var(--verde), #8b9467);
      color: white;
      border-radius: 16px 16px 0 0;
    }

    .modal-header h3 {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 600;
    }

    .modal-close {
      background: none;
      border: none;
      color: white;
      font-size: 24px;
      cursor: pointer;
      padding: 4px;
      border-radius: 4px;
      transition: background-color 0.2s;
    }

    .modal-close:hover {
      background: rgba(255, 255, 255, 0.2);
    }

    .modal-body {
      padding: 24px;
    }

    .payment-info {
      margin-bottom: 24px;
    }

    .info-section {
      margin-bottom: 24px;
      padding: 20px;
      background: rgba(107, 112, 92, 0.05);
      border-radius: 12px;
      border-left: 4px solid var(--verde);
    }

    .info-section h4 {
      margin: 0 0 16px 0;
      color: var(--verde);
      font-size: 1.1rem;
      font-weight: 600;
    }

    .info-grid {
      display: grid;
      gap: 12px;
    }

    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
    }

    .info-item .label {
      font-weight: 600;
      color: var(--gris);
    }

    .info-item .value {
      color: var(--verde);
      font-weight: 500;
    }

    .total-amount {
      font-size: 1.2rem;
      font-weight: 700;
      color: var(--verde);
    }

    .verification-info {
      gap: 16px;
    }

    .code-display {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px;
      background: white;
      border-radius: 8px;
      border: 2px solid var(--verde);
      margin-bottom: 16px;
    }

    .code-value {
      font-family: 'Courier New', monospace;
      font-size: 1.1rem;
      font-weight: bold;
      color: var(--verde);
      background: rgba(107, 112, 92, 0.05);
      padding: 4px 8px;
      border-radius: 4px;
      flex: 1;
    }

    .copy-btn {
      background: var(--verde);
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;
    }

    .copy-btn:hover {
      background: #8b9467;
      transform: translateY(-1px);
    }

    .instructions {
      background: #FAF9F6;
      padding: 16px;
      border-radius: 8px;
      border-left: 4px solid #8C6A4F;
    }

    .instructions p {
      margin: 0 0 12px 0;
      color: #8C6A4F;
      font-weight: 600;
    }

    .instructions ol {
      margin: 0;
      padding-left: 20px;
      color: var(--gris);
    }

    .instructions li {
      margin-bottom: 8px;
      line-height: 1.5;
    }

    .form-help {
      display: block;
      margin-top: 6px;
      font-size: 0.875rem;
      color: #6c757d;
      font-style: italic;
    }

    .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      margin-top: 24px;
      padding-top: 20px;
      border-top: 1px solid rgba(107, 112, 92, 0.1);
    }

    .btn-modern.btn-success {
      background: #28a745;
      color: white;
    }

    .btn-modern.btn-success:hover {
      background: #218838;
    }

    /* Estilos del Modal de Historial */
    .history-timeline {
      position: relative;
      padding-left: 30px;
    }

    .history-timeline::before {
      content: '';
      position: absolute;
      left: 15px;
      top: 0;
      bottom: 0;
      width: 2px;
      background: linear-gradient(to bottom, var(--verde), #8b9467);
    }

    .timeline-item {
      position: relative;
      margin-bottom: 24px;
      background: white;
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      border-left: 4px solid var(--verde);
    }

    .timeline-item::before {
      content: '';
      position: absolute;
      left: -38px;
      top: 20px;
      width: 12px;
      height: 12px;
      background: var(--verde);
      border-radius: 50%;
      border: 3px solid white;
      box-shadow: 0 0 0 2px var(--verde);
    }

    .timeline-item:last-child::after {
      content: '';
      position: absolute;
      left: -35px;
      bottom: -12px;
      width: 6px;
      height: 6px;
      background: #8b9467;
      border-radius: 50%;
    }

    .timeline-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
    }

    .timeline-status {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      background: var(--verde);
      color: white;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 600;
    }

    .timeline-date {
      color: #6c757d;
      font-size: 0.875rem;
      font-weight: 500;
    }

    .timeline-employee {
      color: var(--gris);
      font-size: 0.875rem;
      margin-top: 4px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .timeline-employee i {
      color: var(--verde);
    }

    .no-history {
      text-align: center;
      padding: 40px 20px;
      color: #6c757d;
    }

    .no-history i {
      font-size: 3rem;
      color: #dee2e6;
      margin-bottom: 16px;
    }

    .no-history p {
      margin: 0;
      font-size: 1.1rem;
    }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <!-- Header -->
    <header class="dashboard-header fade-in">
      <div class="header-left">
        <div class="user-avatar">
          <i class="fas fa-user"></i>
        </div>
        <div class="header-info">
          <h1>¡Hola, <?php echo htmlspecialchars($perfil['nombre'] ?: 'Empleado', ENT_QUOTES, 'UTF-8'); ?>!</h1>
          <p>Gestiona tus pedidos y mantén tu perfil actualizado</p>
        </div>
      </div>
      <div class="header-actions">
        <button class="btn-modern btn-secondary" onclick="showQuickActivityForm()">
          <i class="fas fa-plus"></i>
          Nueva Actividad
        </button>
        <a href="/index.php" class="btn-modern btn-secondary">
          <i class="fas fa-home"></i>
          Inicio
        </a>
        <a href="/auth/logout.php" class="btn-modern btn-primary">
          <i class="fas fa-sign-out-alt"></i>
          Cerrar Sesión
        </a>
      </div>
    </header>

    <!-- Alertas -->
    <?php if ($flash_error): ?>
      <div class="alert-modern alert-error fade-in">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>
    <?php if ($flash_success): ?>
      <div class="alert-modern alert-success fade-in">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <!-- Panel de Notificaciones -->
    <!-- Debug: <?php echo "Empleado ID: $empleadoId, Notificaciones: " . count($notificaciones) . ", Actividades pendientes: " . count($actividades_pendientes) . ", Actividades hoy: " . count($actividades_hoy); ?> -->
    <?php if (!empty($notificaciones)): ?>
    <div class="notifications-panel fade-in">
      <div class="notifications-header">
        <h3><i class="fas fa-bell"></i> Notificaciones</h3>
        <span class="notifications-count"><?php echo count($notificaciones); ?></span>
      </div>
      <div class="notifications-grid">
        <?php foreach ($notificaciones as $notif): ?>
        <div class="notification-card notification-<?php echo htmlspecialchars($notif['tipo']); ?>">
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

    <!-- Panel de Actividades del Día -->
    <?php if (!empty($actividades_hoy) || !empty($tareas_urgentes)): ?>
    <div class="activities-panel fade-in">
      <div class="activities-header">
        <h3><i class="fas fa-calendar-day"></i> Mi Agenda de Hoy</h3>
        <div class="activities-summary">
          <span class="activity-count"><?php echo count($actividades_hoy); ?> programadas</span>
          <?php if (!empty($tareas_urgentes)): ?>
          <span class="urgent-count"><?php echo count($tareas_urgentes); ?> urgentes</span>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="activities-content">
        <!-- Tareas Urgentes -->
        <?php if (!empty($tareas_urgentes)): ?>
        <div class="urgent-tasks">
          <h4><i class="fas fa-exclamation-triangle"></i> Tareas Urgentes</h4>
          <div class="tasks-list">
            <?php foreach ($tareas_urgentes as $tarea): ?>
            <div class="task-item urgent">
              <div class="task-icon">
                <?php 
                $icons = ['nota' => '📝', 'llamada' => '📞', 'email' => '📧', 'reunion' => '🤝', 'tarea' => '✅', 'seguimiento' => '🔄'];
                echo $icons[$tarea['tipo']] ?? '📄'; 
                ?>
              </div>
              <div class="task-content">
                <div class="task-title"><?php echo htmlspecialchars($tarea['descripcion']); ?></div>
                <div class="task-client">
                  Cliente: <?php echo htmlspecialchars(($tarea['cliente_nombre'] ?? '') . ' ' . ($tarea['cliente_apellido'] ?? '')); ?>
                </div>
                <?php if ($tarea['fecha_programada']): ?>
                <div class="task-time">
                  <i class="fas fa-clock"></i>
                  <?php echo date('H:i', strtotime($tarea['fecha_programada'])); ?>
                </div>
                <?php endif; ?>
              </div>
              <div class="task-priority priority-<?php echo htmlspecialchars($tarea['prioridad']); ?>">
                <?php echo strtoupper($tarea['prioridad']); ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Actividades de Hoy -->
        <?php if (!empty($actividades_hoy)): ?>
        <div class="today-activities">
          <h4><i class="fas fa-calendar-check"></i> Actividades de Hoy</h4>
          <div class="activities-timeline">
            <?php foreach ($actividades_hoy as $actividad): ?>
            <div class="timeline-item">
              <div class="timeline-time">
                <?php echo $actividad['fecha_programada'] ? date('H:i', strtotime($actividad['fecha_programada'])) : 'Sin hora'; ?>
              </div>
              <div class="timeline-marker">
                <?php 
                $icons = ['nota' => '📝', 'llamada' => '📞', 'email' => '📧', 'reunion' => '🤝', 'tarea' => '✅', 'seguimiento' => '🔄'];
                echo $icons[$actividad['tipo']] ?? '📄'; 
                ?>
              </div>
              <div class="timeline-content">
                <div class="timeline-title"><?php echo htmlspecialchars($actividad['descripcion']); ?></div>
                <div class="timeline-client">
                  <?php echo htmlspecialchars(($actividad['cliente_nombre'] ?? '') . ' ' . ($actividad['cliente_apellido'] ?? '')); ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Navegación por Pestañas -->
    <div class="employee-nav">
      <a href="#" class="nav-tab active" data-tab="tab-dashboard">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <a href="#" class="nav-tab" data-tab="tab-pedidos">
        <i class="fas fa-tasks"></i>
        <span>Pedidos</span>
      </a>
      <a href="#" class="nav-tab" data-tab="tab-actividades">
        <i class="fas fa-calendar-check"></i>
        <span>Actividades</span>
      </a>
      <a href="#" class="nav-tab" data-tab="tab-perfil">
        <i class="fas fa-user-cog"></i>
        <span>Perfil</span>
      </a>
    </div>

    <!-- Contenido de las Pestañas -->
    <div class="tab-content active" id="tab-dashboard">
      <!-- Resumen Rápido -->
      <div class="dashboard-summary fade-in">
        <div class="summary-card">
          <div class="summary-icon">
            <i class="fas fa-tasks"></i>
          </div>
          <div class="summary-content">
            <h3>Mis Tareas</h3>
            <div class="summary-stats">
              <span class="stat-item">
                <strong><?php echo (int)$asignados_a_mi; ?></strong> asignados
              </span>
              <span class="stat-item urgent">
                <strong><?php echo count($tareas_urgentes ?? []); ?></strong> urgentes
              </span>
            </div>
          </div>
        </div>

        <div class="summary-card">
          <div class="summary-icon">
            <i class="fas fa-calendar-day"></i>
          </div>
          <div class="summary-content">
            <h3>Hoy</h3>
            <div class="summary-stats">
              <span class="stat-item">
                <strong><?php echo count($actividades_hoy ?? []); ?></strong> actividades
              </span>
            </div>
          </div>
        </div>

        <div class="summary-card">
          <div class="summary-icon">
            <i class="fas fa-bell"></i>
          </div>
          <div class="summary-content">
            <h3>Notificaciones</h3>
            <div class="summary-stats">
              <span class="stat-item">
                <strong><?php echo count($notificaciones ?? []); ?></strong> nuevas
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tareas Urgentes (Solo las más importantes) -->
      <?php if (!empty($tareas_urgentes) && count($tareas_urgentes) > 0): ?>
      <div class="urgent-section fade-in">
        <div class="section-header">
          <h3><i class="fas fa-exclamation-triangle"></i> Tareas Urgentes</h3>
          <span class="view-all-link">
            <a href="#" onclick="document.querySelector('[data-tab=&quot;tab-actividades&quot;]').click()">Ver todas</a>
          </span>
        </div>
        <div class="urgent-tasks-compact">
          <?php 
          $urgentes_mostrar = array_slice($tareas_urgentes, 0, 3); // Solo mostrar las primeras 3
          foreach ($urgentes_mostrar as $tarea): 
          ?>
          <div class="task-compact">
            <div class="task-icon">
              <?php 
              $icons = ['nota' => '📝', 'llamada' => '📞', 'email' => '📧', 'reunion' => '🤝', 'tarea' => '✅', 'seguimiento' => '🔄'];
              echo $icons[$tarea['tipo']] ?? '📄'; 
              ?>
            </div>
            <div class="task-info">
              <div class="task-title"><?php echo htmlspecialchars($tarea['descripcion']); ?></div>
              <div class="task-meta">
                <?php echo htmlspecialchars(($tarea['cliente_nombre'] ?? '') . ' ' . ($tarea['cliente_apellido'] ?? '')); ?>
                <?php if ($tarea['fecha_programada']): ?>
                  • <?php echo date('H:i', strtotime($tarea['fecha_programada'])); ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="task-priority priority-<?php echo htmlspecialchars($tarea['prioridad']); ?>">
              <?php echo strtoupper($tarea['prioridad']); ?>
            </div>
          </div>
          <?php endforeach; ?>
          
          <?php if (count($tareas_urgentes) > 3): ?>
          <div class="more-tasks">
            <a href="#" onclick="document.querySelector('[data-tab=&quot;tab-actividades&quot;]').click()">
              +<?php echo count($tareas_urgentes) - 3; ?> tareas más
            </a>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Estadísticas Principales (Reducidas) -->
      <div class="stats-grid-compact">
        <div class="stat-card-compact fade-in">
          <div class="stat-icon">
            <i class="fas fa-clipboard-list"></i>
          </div>
          <div class="stat-content">
            <div class="stat-number"><?php echo (int)$total_pedidos; ?></div>
            <div class="stat-label">Total Pedidos</div>
          </div>
        </div>
        
        <div class="stat-card-compact fade-in">
          <div class="stat-icon">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stat-content">
            <div class="stat-number"><?php echo (int)$cont_pendientes; ?></div>
            <div class="stat-label">Pendientes</div>
          </div>
        </div>
        
        <div class="stat-card-compact fade-in">
          <div class="stat-icon">
            <i class="fas fa-dollar-sign"></i>
          </div>
          <div class="stat-content">
            <div class="stat-number"><?php echo (int)$pagos_exitosos; ?></div>
            <div class="stat-label">Pagos Confirmados</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pestaña de Pedidos -->
    <div class="tab-content" id="tab-pedidos">
      <div class="content-card fade-in">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-tasks"></i>
            Gestión de Pedidos
          </h2>
        </div>
        
        <!-- Filtros -->
        <div class="filters-container">
          <input id="filtroBusqueda" class="filter-input" type="text" placeholder="🔍 Buscar por ID, cliente o plan...">
          <select id="filtroPago" class="filter-input">
            <option value="">💳 Estado de Pago</option>
            <option value="pagado">Pagado</option>
            <option value="pendiente">Pendiente</option>
            <option value="ninguno">Sin Factura</option>
          </select>
          <select id="filtroAsignacion" class="filter-input">
            <option value="">👤 Asignación</option>
            <option value="mi">Asignado a mí</option>
            <option value="sin_asignar">Sin asignar</option>
            <option value="otro">Asignado a otro</option>
          </select>
        </div>
        
        <!-- Tabla de Pedidos -->
        <?php if (!empty($pedidos)): ?>
        <div style="overflow-x: auto;">
          <table class="modern-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Plan</th>
                <th>Total</th>
                <th>Estado Pago</th>
                <th>Asignación</th>
                <th>Historial</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pedidos as $p): ?>
              <?php 
                $codigo = isset($p['factura_id']) ? generar_codigo_factura((int)$p['factura_id'], $p['fecha_factura'] ?? '') : null;
                $asignado_a_mi = ((int)($p['empleado_id'] ?? 0) === $empleadoId);
                $asignacion_flag = $asignado_a_mi ? 'mi' : ((int)($p['empleado_id'] ?? 0) === 0 ? 'sin_asignar' : 'otro');
                $pago_flag = (($p['estado_pago'] ?? '') === 'pagado') ? 'pagado' : ((($p['estado_pago'] ?? '') === 'pendiente' && !empty($p['factura_id'])) ? 'pendiente' : 'ninguno');
                $cliente_nombre = (string)($p['cliente_nombre'] ?? '');
                $plan_nombre = (string)($p['nombre_plan'] ?? '');
              ?>
              <tr data-id="<?php echo (int)$p['id']; ?>" 
                  data-cliente="<?php echo htmlspecialchars(strtolower($cliente_nombre), ENT_QUOTES, 'UTF-8'); ?>" 
                  data-plan="<?php echo htmlspecialchars(strtolower($plan_nombre), ENT_QUOTES, 'UTF-8'); ?>" 
                  data-pago="<?php echo htmlspecialchars($pago_flag, ENT_QUOTES, 'UTF-8'); ?>" 
                  data-asignacion="<?php echo htmlspecialchars($asignacion_flag, ENT_QUOTES, 'UTF-8'); ?>">
                <td><strong>#<?php echo (int)$p['id']; ?></strong></td>
                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($p['fecha'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($p['cliente_nombre'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($p['nombre_plan'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><strong>$<?php echo number_format((float)($p['total'] ?? 0), 2); ?></strong></td>
                <td>
                  <?php if (($p['estado_pago'] ?? '') === 'pagado'): ?>
                    <span class="badge-modern badge-success">
                      <i class="fas fa-check"></i> Pagado
                    </span>
                    <?php if ($codigo): ?>
                      <div style="font-size: 11px; color: #666; margin-top: 4px;">Código: <?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                  <?php elseif (($p['estado_pago'] ?? '') === 'pendiente' && !empty($p['factura_id'])): ?>
                    <span class="badge-modern badge-warning">
                      <i class="fas fa-clock"></i> Pendiente
                    </span>
                    <?php if ($codigo): ?>
                      <div style="font-size: 11px; color: #666; margin-top: 4px;">Código: <?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge-modern badge-neutral">
                      <i class="fas fa-minus"></i> Sin Factura
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($asignado_a_mi): ?>
                    <span class="badge-modern badge-info">
                      <i class="fas fa-user-check"></i> Asignado a mí
                    </span>
                  <?php elseif ((int)($p['empleado_id'] ?? 0) === 0): ?>
                    <span class="badge-modern badge-neutral">
                      <i class="fas fa-user-plus"></i> Sin asignar
                    </span>
                  <?php else: ?>
                    <span class="badge-modern badge-neutral">
                      <i class="fas fa-user"></i> Asignado a otro
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="btn-action secondary" onclick="mostrarHistorial(<?php echo (int)$p['id']; ?>, '<?php echo htmlspecialchars($p['cliente_nombre'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>')">
                    <i class="fas fa-history"></i> Ver Historial
                  </button>
                </td>
                <td>
                  <div class="action-buttons">
                    <?php if (empty($p['empleado_id'])): ?>
                      <form method="post" action="/empleado/tomar_pedido.php" style="display: inline;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                        <button class="btn-action primary" type="submit">
                          <i class="fas fa-hand-paper"></i> Tomar
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php
                      $estadoActual = strtolower((string)($p['estado'] ?? ''));
                      $asignadoA = (int)($p['empleado_id'] ?? 0);
                      $puedeIniciar = ($estadoActual === 'pendiente') && ($asignadoA === 0 || $asignadoA === $empleadoId);
                      $puedeCompletar = in_array($estadoActual, ['pendiente','enviado'], true) && ($asignadoA === $empleadoId) && (($p['estado_pago'] ?? '') === 'pagado');
                      $puedeCancelar = in_array($estadoActual, ['pendiente','enviado'], true) && ($asignadoA === 0 || $asignadoA === $empleadoId);
                    ?>
                    
                    <?php if ($puedeIniciar): ?>
                      <form method="post" action="/empleado/cambiar_estado.php" style="display: inline;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="accion" value="iniciar">
                        <button class="btn-action primary" type="submit">
                          <i class="fas fa-play"></i> Iniciar
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php if ($puedeCompletar): ?>
                      <form method="post" action="/empleado/cambiar_estado.php" style="display: inline;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="accion" value="completar">
                        <button class="btn-action success" type="submit">
                          <i class="fas fa-check"></i> Completar
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php if ($puedeCancelar): ?>
                      <form method="post" action="/empleado/cambiar_estado.php" style="display: inline;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="accion" value="cancelar">
                        <button class="btn-action danger" type="submit">
                          <i class="fas fa-times"></i> Cancelar
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php if (!empty($p['factura_id']) && ($p['estado_pago'] ?? 'pendiente') !== 'pagado'): ?>
                      <button class="btn-action info" onclick="mostrarModalPago(<?php echo (int)$p['id']; ?>, '<?php echo htmlspecialchars($codigo ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($p['cliente_nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($p['nombre_plan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo (float)($p['total'] ?? 0); ?>)">
                        <i class="fas fa-credit-card"></i> Confirmar Pago
                      </button>
                    <?php endif; ?>

                    <!-- Ver archivos del pedido -->
                    <a class="btn-action secondary" href="/empleado/archivos.php?pedido_id=<?php echo (int)$p['id']; ?>">
                      <i class="fas fa-folder"></i> Archivos
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-clipboard-list" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
            <p>No hay pedidos disponibles en este momento.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Pestaña de Actividades -->
    <div class="tab-content" id="tab-actividades">
      <div class="content-card fade-in">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-calendar-check"></i>
            Gestión de Actividades
          </h2>
        </div>
        
        <!-- Panel completo de actividades -->
        <div class="activities-content">
          <!-- Actividades Pendientes -->
          <?php if (!empty($actividades_pendientes)): ?>
          <div class="urgent-tasks">
            <h4><i class="fas fa-list-ul"></i> Actividades Pendientes</h4>
            <div class="tasks-list">
              <?php foreach ($actividades_pendientes as $actividad): ?>
              <div class="task-item" data-actividad-id="<?php echo is_numeric($actividad['id'] ?? null) ? (int)$actividad['id'] : ''; ?>" <?php echo is_numeric($actividad['id'] ?? null) ? '' : 'data-demo="1"'; ?> >
                <div class="task-icon">
                  <?php 
                  $icons = ['nota' => '📝', 'llamada' => '📞', 'email' => '📧', 'reunion' => '🤝', 'tarea' => '✅', 'seguimiento' => '🔄'];
                  echo $icons[$actividad['tipo']] ?? '📄'; 
                  ?>
                </div>
                <div class="task-content">
                  <div class="task-title"><?php echo htmlspecialchars($actividad['descripcion']); ?></div>
                  <div class="task-client">
                    Cliente: <?php echo htmlspecialchars(($actividad['cliente_nombre'] ?? '') . ' ' . ($actividad['cliente_apellido'] ?? '')); ?>
                  </div>
                  <?php if ($actividad['fecha_programada']): ?>
                  <div class="task-time">
                    <i class="fas fa-clock"></i>
                    <?php echo date('d/m/Y H:i', strtotime($actividad['fecha_programada'])); ?>
                  </div>
                  <?php endif; ?>
                </div>
                <div class="task-priority priority-<?php echo htmlspecialchars($actividad['prioridad']); ?>">
                  <?php echo strtoupper($actividad['prioridad']); ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Actividades de Hoy -->
          <?php if (!empty($actividades_hoy)): ?>
          <div class="today-activities">
            <h4><i class="fas fa-calendar-day"></i> Agenda de Hoy</h4>
            <div class="activities-timeline">
              <?php foreach ($actividades_hoy as $actividad): ?>
              <div class="timeline-item">
                <div class="timeline-time">
                  <?php echo $actividad['fecha_programada'] ? date('H:i', strtotime($actividad['fecha_programada'])) : 'Sin hora'; ?>
                </div>
                <div class="timeline-marker">
                  <?php 
                  $icons = ['nota' => '📝', 'llamada' => '📞', 'email' => '📧', 'reunion' => '🤝', 'tarea' => '✅', 'seguimiento' => '🔄'];
                  echo $icons[$actividad['tipo']] ?? '📄'; 
                  ?>
                </div>
                <div class="timeline-content">
                  <div class="timeline-title"><?php echo htmlspecialchars($actividad['descripcion']); ?></div>
                  <div class="timeline-client">
                    <?php echo htmlspecialchars(($actividad['cliente_nombre'] ?? '') . ' ' . ($actividad['cliente_apellido'] ?? '')); ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Pestaña de Perfil -->
    <div class="tab-content" id="tab-perfil">
      <div class="content-card fade-in">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-user-cog"></i>
            Mi Perfil
          </h2>
        </div>
        
        <form method="post" class="profile-form">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="accion" value="actualizar_perfil">
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Nombre Completo</label>
              <input class="form-input" type="text" name="nombre" value="<?php echo htmlspecialchars($perfil['nombre'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Tu nombre completo">
            </div>
            <div class="form-group">
              <label class="form-label">Teléfono</label>
              <input class="form-input" type="text" name="telefono" value="<?php echo htmlspecialchars($perfil['telefono'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Tu número de teléfono">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Correo Electrónico</label>
              <input class="form-input" type="email" name="correo" value="<?php echo htmlspecialchars($perfil['correo'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu@correo.com">
            </div>
            <div class="form-group">
              <label class="form-label">Nueva Contraseña (opcional)</label>
              <input class="form-input" type="password" name="password" placeholder="••••••••">
            </div>
          </div>
          
          <button class="btn-modern btn-primary" type="submit" style="width: 100%; justify-content: center;">
            <i class="fas fa-save"></i>
            Guardar Cambios
          </button>
        </form>
      </div>
    </div>

    <!-- Contenido Principal -->
    <div class="main-content" style="display: none;">
      <!-- Lista de Pedidos -->
      <div class="content-card fade-in">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-tasks"></i>
            Gestión de Pedidos
          </h2>
        </div>
        
        <!-- Filtros -->
        <div class="filters-container">
          <input id="filtroBusqueda" class="filter-input" type="text" placeholder="🔍 Buscar por ID, cliente o plan...">
          <select id="filtroPago" class="filter-input">
            <option value="">💳 Estado de Pago</option>
            <option value="pagado">Pagado</option>
            <option value="pendiente">Pendiente</option>
            <option value="ninguno">Sin Factura</option>
          </select>
          <select id="filtroAsignacion" class="filter-input">
            <option value="">👤 Asignación</option>
            <option value="mi">Asignado a mí</option>
            <option value="sin_asignar">Sin asignar</option>
            <option value="otro">Asignado a otro</option>
          </select>
        </div>
        
        <!-- Tabla de Pedidos -->
        <?php if (!empty($pedidos)): ?>
        <div style="overflow-x: auto;">
          <table class="modern-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Plan</th>
                <th>Total</th>
                <th>Estado Pago</th>
                <th>Asignación</th>
                <th>Historial</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pedidos as $p): ?>
              <?php 
                $codigo = isset($p['factura_id']) ? generar_codigo_factura((int)$p['factura_id'], $p['fecha_factura'] ?? '') : null;
                $asignado_a_mi = ((int)($p['empleado_id'] ?? 0) === $empleadoId);
                $asignacion_flag = $asignado_a_mi ? 'mi' : ((int)($p['empleado_id'] ?? 0) === 0 ? 'sin_asignar' : 'otro');
                $pago_flag = (($p['estado_pago'] ?? '') === 'pagado') ? 'pagado' : ((($p['estado_pago'] ?? '') === 'pendiente' && !empty($p['factura_id'])) ? 'pendiente' : 'ninguno');
                $cliente_nombre = (string)($p['cliente_nombre'] ?? '');
                $plan_nombre = (string)($p['nombre_plan'] ?? '');
              ?>
              <tr data-id="<?php echo (int)$p['id']; ?>" 
                  data-cliente="<?php echo htmlspecialchars(strtolower($cliente_nombre), ENT_QUOTES, 'UTF-8'); ?>" 
                  data-plan="<?php echo htmlspecialchars(strtolower($plan_nombre), ENT_QUOTES, 'UTF-8'); ?>" 
                  data-pago="<?php echo htmlspecialchars($pago_flag, ENT_QUOTES, 'UTF-8'); ?>" 
                  data-asignacion="<?php echo htmlspecialchars($asignacion_flag, ENT_QUOTES, 'UTF-8'); ?>">
                <td><strong>#<?php echo (int)$p['id']; ?></strong></td>
                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($p['fecha'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($p['cliente_nombre'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($p['nombre_plan'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><strong>$<?php echo number_format((float)($p['total'] ?? 0), 2); ?></strong></td>
                <td>
                  <?php if (($p['estado_pago'] ?? '') === 'pagado'): ?>
                    <span class="badge-modern badge-success">
                      <i class="fas fa-check"></i> Pagado
                    </span>
                    <?php if ($codigo): ?>
                      <div style="font-size: 11px; color: #666; margin-top: 4px;">Código: <?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                  <?php elseif (($p['estado_pago'] ?? '') === 'pendiente' && !empty($p['factura_id'])): ?>
                    <span class="badge-modern badge-warning">
                      <i class="fas fa-clock"></i> Pendiente
                    </span>
                    <?php if ($codigo): ?>
                      <div style="font-size: 11px; color: #666; margin-top: 4px;">Código: <?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="badge-modern badge-neutral">
                      <i class="fas fa-minus"></i> Sin Factura
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($asignado_a_mi): ?>
                    <span class="badge-modern badge-info">
                      <i class="fas fa-user-check"></i> Asignado a mí
                    </span>
                  <?php elseif ((int)($p['empleado_id'] ?? 0) === 0): ?>
                    <span class="badge-modern badge-neutral">
                      <i class="fas fa-user-plus"></i> Sin asignar
                    </span>
                  <?php else: ?>
                    <span class="badge-modern badge-neutral">
                      <i class="fas fa-user"></i> Asignado a otro
                    </span>
                  <?php endif; ?>
                </td>
                <td>
                  <button class="btn-action secondary" onclick="mostrarHistorial(<?php echo (int)$p['id']; ?>, '<?php echo htmlspecialchars($p['cliente_nombre'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>')">
                    <i class="fas fa-history"></i> Ver Historial
                  </button>
                </td>
                <td>
                  <div class="action-buttons">
                    <?php if (empty($p['empleado_id'])): ?>
                      <form method="post" action="/empleado/tomar_pedido.php" style="display: inline;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                        <button class="btn-action primary" type="submit">
                          <i class="fas fa-hand-paper"></i> Tomar
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php
                      $estadoActual = strtolower((string)($p['estado'] ?? ''));
                      $asignadoA = (int)($p['empleado_id'] ?? 0);
                      $puedeIniciar = ($estadoActual === 'pendiente') && ($asignadoA === 0 || $asignadoA === $empleadoId);
                      $puedeCompletar = in_array($estadoActual, ['pendiente','enviado'], true) && ($asignadoA === $empleadoId) && (($p['estado_pago'] ?? '') === 'pagado');
                      $puedeCancelar = in_array($estadoActual, ['pendiente','enviado'], true) && ($asignadoA === 0 || $asignadoA === $empleadoId);
                    ?>
                    
                    <?php if ($puedeIniciar): ?>
                      <form method="post" action="/empleado/cambiar_estado.php" style="display: inline;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="accion" value="iniciar">
                        <button class="btn-action primary" type="submit">
                          <i class="fas fa-play"></i> Iniciar
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php if ($puedeCompletar): ?>
                      <form method="post" action="/empleado/cambiar_estado.php" style="display: inline;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="accion" value="completar">
                        <button class="btn-action success" type="submit">
                          <i class="fas fa-check"></i> Completar
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php if ($puedeCancelar): ?>
                      <form method="post" action="/empleado/cambiar_estado.php" style="display: inline;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                        <input type="hidden" name="accion" value="cancelar">
                        <button class="btn-action danger" type="submit">
                          <i class="fas fa-times"></i> Cancelar
                        </button>
                      </form>
                    <?php endif; ?>
                    
                    <?php if (!empty($p['factura_id']) && ($p['estado_pago'] ?? 'pendiente') !== 'pagado'): ?>
                      <button class="btn-action info" onclick="mostrarModalPago(<?php echo (int)$p['id']; ?>, '<?php echo htmlspecialchars($codigo ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($p['cliente_nombre'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($p['nombre_plan'] ?? '', ENT_QUOTES, 'UTF-8'); ?>', <?php echo (float)($p['total'] ?? 0); ?>)">
                        <i class="fas fa-credit-card"></i> Confirmar Pago
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-clipboard-list" style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;"></i>
            <p>No hay pedidos disponibles en este momento.</p>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Panel de Perfil -->
      <div class="content-card fade-in">
        <div class="card-header">
          <h2 class="card-title">
            <i class="fas fa-user-cog"></i>
            Mi Perfil
          </h2>
        </div>
        
        <form method="post" class="profile-form">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="accion" value="actualizar_perfil">
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Nombre Completo</label>
              <input class="form-input" type="text" name="nombre" value="<?php echo htmlspecialchars($perfil['nombre'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Tu nombre completo">
            </div>
            <div class="form-group">
              <label class="form-label">Teléfono</label>
              <input class="form-input" type="text" name="telefono" value="<?php echo htmlspecialchars($perfil['telefono'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Tu número de teléfono">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Correo Electrónico</label>
              <input class="form-input" type="email" name="correo" value="<?php echo htmlspecialchars($perfil['correo'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="tu@correo.com">
            </div>
            <div class="form-group">
              <label class="form-label">Nueva Contraseña (opcional)</label>
              <input class="form-input" type="password" name="password" placeholder="••••••••">
            </div>
          </div>
          
          <button class="btn-modern btn-primary" type="submit" style="width: 100%; justify-content: center;">
            <i class="fas fa-save"></i>
            Guardar Cambios
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Confirmación de Pago -->
  <div id="modalConfirmarPago" class="modal-overlay" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-credit-card"></i> Confirmar Pago del Plan</h3>
        <button class="modal-close" onclick="cerrarModalPago()">&times;</button>
      </div>
      
      <div class="modal-body">
        <div class="payment-info">
          <div class="info-section">
            <h4><i class="fas fa-user"></i> Información del Cliente</h4>
            <div class="info-grid">
              <div class="info-item">
                <span class="label">Cliente:</span>
                <span id="modal-cliente" class="value"></span>
              </div>
              <div class="info-item">
                <span class="label">Plan:</span>
                <span id="modal-plan" class="value"></span>
              </div>
              <div class="info-item">
                <span class="label">Monto Total:</span>
                <span id="modal-total" class="value total-amount"></span>
              </div>
            </div>
          </div>
          
          <div class="info-section">
            <h4><i class="fas fa-shield-alt"></i> Verificación de Pago</h4>
            <div class="verification-info">
              <div class="code-display">
                <span class="label">Código de Verificación:</span>
                <span id="modal-codigo" class="code-value"></span>
                <button class="copy-btn" onclick="copiarCodigo()" title="Copiar código">
                  <i class="fas fa-copy"></i>
                </button>
              </div>
              <div class="instructions">
                <p><i class="fas fa-info-circle"></i> <strong>Instrucciones:</strong></p>
                <ol>
                  <li>Solicita al cliente que proporcione el código de verificación de su comprobante de pago</li>
                  <li>Verifica que el código coincida exactamente con el mostrado arriba</li>
                  <li>Confirma que el monto pagado corresponde al total del plan</li>
                  <li>Ingresa el código en el campo de abajo para confirmar el pago</li>
                </ol>
              </div>
            </div>
          </div>
        </div>
        
        <form id="formConfirmarPago" method="post" action="/empleado/confirmar_pago.php">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" id="modal-pedido-id" name="pedido_id" value="">
          
          <div class="form-group">
            <label class="form-label">
              <i class="fas fa-key"></i> Código de Verificación del Cliente
            </label>
            <input class="form-input" type="text" name="codigo" id="codigo-input" placeholder="Ingresa el código proporcionado por el cliente" required autocomplete="off">
            <small class="form-help">El cliente debe proporcionar este código desde su comprobante de pago</small>
          </div>
          
          <div class="modal-actions">
            <button type="button" class="btn-modern btn-secondary" onclick="cerrarModalPago()">
              <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" class="btn-modern btn-success">
              <i class="fas fa-check"></i> Confirmar Pago
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal de Historial -->
  <div id="modalHistorial" class="modal-overlay" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h3><i class="fas fa-history"></i> Historial del Pedido</h3>
        <button type="button" class="modal-close" onclick="cerrarModalHistorial()">&times;</button>
      </div>
      <div class="modal-body">
        <div class="info-section">
          <h4>Información del Pedido</h4>
          <div class="info-grid">
            <div class="info-item">
              <span class="label">Pedido ID:</span>
              <span class="value" id="historial-pedido-id">#000</span>
            </div>
            <div class="info-item">
              <span class="label">Cliente:</span>
              <span class="value" id="historial-cliente">—</span>
            </div>
          </div>
        </div>
        
        <div class="info-section">
          <h4>Historial de Estados</h4>
          <div id="historial-contenido" class="history-timeline">
            <!-- El contenido se cargará dinámicamente -->
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-modern btn-secondary" onclick="cerrarModalHistorial()">
          <i class="fas fa-times"></i> Cerrar
        </button>
      </div>
    </div>
  </div>

  <script>
    // Funcionalidad de navegación entre pestañas
    document.addEventListener('DOMContentLoaded', function() {
      const navTabs = document.querySelectorAll('.employee-nav .nav-tab');
      const tabContents = document.querySelectorAll('.tab-content');

      navTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
          e.preventDefault();
          
          const targetTab = this.getAttribute('data-tab');
          
          // Remover clase active de todas las pestañas
          navTabs.forEach(t => t.classList.remove('active'));
          tabContents.forEach(content => content.classList.remove('active'));
          
          // Agregar clase active a la pestaña clickeada
          this.classList.add('active');
          
          // Mostrar el contenido correspondiente
          const targetContent = document.getElementById(targetTab);
          if (targetContent) {
            targetContent.classList.add('active');
          }
        });
      });
    });

    // Filtros de pedidos
    (function() {
      const searchInput = document.getElementById('filtroBusqueda');
      const paymentFilter = document.getElementById('filtroPago');
      const assignmentFilter = document.getElementById('filtroAsignacion');
      const tableBody = document.querySelector('.modern-table tbody');
      
      if (!tableBody) return;
      
      function normalizeText(text) {
        return (text || '').toString().trim().toLowerCase();
      }
      
      function matchesFilters(row) {
        const id = row.getAttribute('data-id') || '';
        const cliente = row.getAttribute('data-cliente') || '';
        const plan = row.getAttribute('data-plan') || '';
        const pago = row.getAttribute('data-pago') || '';
        const asignacion = row.getAttribute('data-asignacion') || '';
        
        const searchTerm = normalizeText(searchInput?.value);
        const paymentValue = paymentFilter?.value || '';
        const assignmentValue = assignmentFilter?.value || '';
        
        const matchesSearch = !searchTerm || 
          id.indexOf(searchTerm) !== -1 || 
          cliente.indexOf(searchTerm) !== -1 || 
          plan.indexOf(searchTerm) !== -1;
          
        const matchesPayment = !paymentValue || pago === paymentValue;
        const matchesAssignment = !assignmentValue || asignacion === assignmentValue;
        
        return matchesSearch && matchesPayment && matchesAssignment;
      }
      
      function applyFilters() {
        const rows = tableBody.querySelectorAll('tr');
        rows.forEach(row => {
          row.style.display = matchesFilters(row) ? '' : 'none';
        });
      }
      
      // Event listeners
      [searchInput, paymentFilter, assignmentFilter].forEach(element => {
        if (element) {
          ['input', 'change'].forEach(event => {
            element.addEventListener(event, applyFilters);
          });
        }
      });
      
      // Aplicar filtros iniciales
      applyFilters();
    })();
    
    // Funciones del Modal de Confirmación de Pago
    function mostrarModalPago(pedidoId, codigo, cliente, plan, total) {
      document.getElementById('modal-pedido-id').value = pedidoId;
      document.getElementById('modal-cliente').textContent = cliente || 'No especificado';
      document.getElementById('modal-plan').textContent = plan || 'No especificado';
      document.getElementById('modal-total').textContent = '$' + parseFloat(total || 0).toLocaleString('es-CO', {minimumFractionDigits: 2});
      document.getElementById('modal-codigo').textContent = codigo || 'No disponible';
      document.getElementById('codigo-input').value = '';
      document.getElementById('modalConfirmarPago').style.display = 'flex';
      
      // Enfocar el campo de código
      setTimeout(() => {
        document.getElementById('codigo-input').focus();
      }, 100);
    }
    
    function cerrarModalPago() {
      document.getElementById('modalConfirmarPago').style.display = 'none';
    }
    
    function copiarCodigo() {
      const codigo = document.getElementById('modal-codigo').textContent;
      if (codigo && codigo !== 'No disponible') {
        navigator.clipboard.writeText(codigo).then(() => {
          const btn = document.querySelector('.copy-btn');
          const originalHTML = btn.innerHTML;
          btn.innerHTML = '<i class="fas fa-check"></i>';
          btn.style.color = '#28a745';
          setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.style.color = '';
          }, 2000);
        }).catch(() => {
          // Fallback para navegadores que no soportan clipboard API
          const textArea = document.createElement('textarea');
          textArea.value = codigo;
          document.body.appendChild(textArea);
          textArea.select();
          document.execCommand('copy');
          document.body.removeChild(textArea);
        });
      }
    }
    
    // Cerrar modal al hacer clic fuera de él
    document.getElementById('modalConfirmarPago').addEventListener('click', function(e) {
      if (e.target === this) {
        cerrarModalPago();
      }
    });
    
    // Cerrar modal con tecla Escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        if (document.getElementById('modalConfirmarPago').style.display === 'flex') {
          cerrarModalPago();
        }
        if (document.getElementById('modalHistorial').style.display === 'flex') {
          cerrarModalHistorial();
        }
      }
    });
    
    // Funciones del Modal de Historial
    function mostrarHistorial(pedidoId, cliente) {
      document.getElementById('historial-pedido-id').textContent = '#' + pedidoId;
      document.getElementById('historial-cliente').textContent = cliente || '—';
      
      // Obtener el historial del pedido desde los datos PHP
      const historialData = <?php echo json_encode($historialPorPedido, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      const historial = historialData[pedidoId] || [];
      
      const contenidoHistorial = document.getElementById('historial-contenido');
      
      if (historial.length === 0) {
        contenidoHistorial.innerHTML = `
          <div class="no-history">
            <i class="fas fa-history"></i>
            <p>Sin movimientos registrados</p>
          </div>
        `;
      } else {
        let timelineHTML = '';
        historial.forEach((item, index) => {
          const fecha = new Date(item.created_at).toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
          });
          
          const estadoAnterior = (item.estado_anterior || '').toUpperCase();
          const estadoNuevo = (item.estado_nuevo || '').toUpperCase();
          const empleado = item.empleado_nombre || 'Sistema';
          
          timelineHTML += `
            <div class="timeline-item">
              <div class="timeline-header">
                <div class="timeline-status">
                  <i class="fas fa-arrow-right"></i>
                  ${estadoAnterior} → ${estadoNuevo}
                </div>
                <div class="timeline-date">${fecha}</div>
              </div>
              <div class="timeline-employee">
                <i class="fas fa-user"></i>
                Por: ${empleado}
              </div>
            </div>
          `;
        });
        contenidoHistorial.innerHTML = timelineHTML;
      }
      
      document.getElementById('modalHistorial').style.display = 'flex';
    }
    
    function cerrarModalHistorial() {
      document.getElementById('modalHistorial').style.display = 'none';
    }
    
    // Cerrar modal del historial al hacer clic fuera de él
    document.getElementById('modalHistorial').addEventListener('click', function(e) {
      if (e.target === this) {
        cerrarModalHistorial();
      }
    });
    
    // Funciones para notificaciones
    function dismissNotification(element) {
      element.style.transform = 'translateX(100%)';
      element.style.opacity = '0';
      setTimeout(() => {
        element.remove();
        updateNotificationCount();
      }, 300);
    }

    function updateNotificationCount() {
      const count = document.querySelectorAll('.notification-card').length;
      const countElement = document.querySelector('.notifications-count');
      if (countElement) {
        countElement.textContent = count;
        if (count === 0) {
          document.querySelector('.notifications-panel').style.display = 'none';
        }
      }
    }

    // Función para marcar tarea como completada (con actualización en servidor)
    function markTaskCompleted(taskElement) {
      const actividadId = taskElement?.dataset?.actividadId ? parseInt(taskElement.dataset.actividadId, 10) : 0;
      if (!actividadId || isNaN(actividadId)) {
        showToast('No se pudo identificar la actividad', 'error');
        return;
      }

      // Feedback inmediato
      taskElement.style.opacity = '0.6';
      taskElement.style.transform = 'scale(0.98)';

      const csrf = '<?php echo htmlspecialchars($token ?? "", ENT_QUOTES, "UTF-8"); ?>';
      const body = new URLSearchParams({ csrf, actividad_id: actividadId });

      fetch('/empleado/actividad_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
      })
      .then(res => res.json())
      .then(data => {
        if (data && data.ok) {
          taskElement.remove();
          updateTaskCounts();
          showToast('Actividad marcada como completada', 'success');
        } else {
          taskElement.style.opacity = '';
          taskElement.style.transform = '';
          showToast(data?.error || 'No se pudo completar la actividad', 'error');
        }
      })
      .catch(() => {
        taskElement.style.opacity = '';
        taskElement.style.transform = '';
        showToast('Error de conexión al actualizar actividad', 'error');
      });
    }

    function updateTaskCounts() {
      const urgentCount = document.querySelectorAll('.task-item.urgent').length;
      const urgentCountElement = document.querySelector('.urgent-count');
      if (urgentCountElement) {
        urgentCountElement.textContent = urgentCount + ' urgentes';
        if (urgentCount === 0) {
          urgentCountElement.style.display = 'none';
        }
      }
    }

    // Función para mostrar notificación toast
    function showToast(message, type = 'info') {
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      toast.innerHTML = `
        <div class="toast-content">
          <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
          <span>${message}</span>
        </div>
      `;
      
      toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 20px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        z-index: 9999;
        transform: translateX(100%);
        transition: all 0.3s ease;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      `;
      
      switch(type) {
        case 'success':
          toast.style.background = 'linear-gradient(135deg, #10b981, #34d399)';
          break;
        case 'error':
          toast.style.background = 'linear-gradient(135deg, #ef4444, #f87171)';
          break;
        case 'warning':
          toast.style.background = 'linear-gradient(135deg, #D2B48C, #F5DEB3)';
          break;
        default:
          toast.style.background = 'linear-gradient(135deg, #3b82f6, #60a5fa)';
      }
      
      document.body.appendChild(toast);
      
      setTimeout(() => {
        toast.style.transform = 'translateX(0)';
      }, 100);
      
      setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
          toast.remove();
        }, 300);
      }, 3000);
    }

    // Animaciones de entrada
    document.addEventListener('DOMContentLoaded', function() {
      const elements = document.querySelectorAll('.fade-in');
      elements.forEach((el, index) => {
        setTimeout(() => {
          el.style.opacity = '1';
          el.style.transform = 'translateY(0)';
        }, index * 100);
      });

      // Hacer notificaciones clickeables
      document.querySelectorAll('.notification-card').forEach(card => {
        card.addEventListener('click', function() {
          const action = this.dataset.action;
          if (action) {
            const element = document.querySelector(action);
            if (element) {
              element.scrollIntoView({ behavior: 'smooth' });
            }
          }
          dismissNotification(this);
        });
      });

      // Hacer tareas clickeables para marcar como completadas
      document.querySelectorAll('.task-item').forEach(task => {
        task.addEventListener('click', function() {
          if (this.dataset.demo === '1') {
            showToast('Actividad de demostración: no se actualiza BD ni perfil del cliente', 'warning');
            return;
          }
          if (confirm('¿Marcar esta tarea como completada?')) {
            markTaskCompleted(this);
          }
        });
      });

      // Auto-refresh cada 5 minutos para notificaciones
      setInterval(() => {
        // Aquí se podría hacer una llamada AJAX para actualizar notificaciones
        console.log('Verificando nuevas notificaciones...');
      }, 300000); // 5 minutos
    });

    // Función para mostrar formulario de actividad rápida
    function showQuickActivityForm() {
      const modal = document.createElement('div');
      modal.className = 'quick-activity-modal';
      modal.innerHTML = `
        <div class="modal-content">
          <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Nueva Actividad Rápida</h3>
            <button class="modal-close" onclick="this.closest('.quick-activity-modal').remove()">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <form class="quick-activity-form">
            <div class="form-group">
              <label>Tipo de actividad:</label>
              <select name="tipo" required>
                <option value="llamada">📞 Llamada</option>
                <option value="email">📧 Email</option>
                <option value="reunion">🤝 Reunión</option>
                <option value="tarea">✅ Tarea</option>
                <option value="seguimiento">🔄 Seguimiento</option>
                <option value="nota">📝 Nota</option>
              </select>
            </div>
            <div class="form-group">
              <label>Descripción:</label>
              <textarea name="descripcion" placeholder="Describe la actividad..." required></textarea>
            </div>
            <div class="form-group">
              <label>Prioridad:</label>
              <select name="prioridad" required>
                <option value="baja">🟢 Baja</option>
                <option value="media" selected>🟡 Media</option>
                <option value="alta">🔴 Alta</option>
              </select>
            </div>
            <div class="form-group">
              <label>Fecha programada:</label>
              <input type="datetime-local" name="fecha_programada">
            </div>
            <div class="form-actions">
              <button type="button" class="btn-cancel" onclick="this.closest('.quick-activity-modal').remove()">
                Cancelar
              </button>
              <button type="submit" class="btn-create">
                <i class="fas fa-plus"></i> Crear Actividad
              </button>
            </div>
          </form>
        </div>
      `;

      modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        animation: fadeIn 0.3s ease;
      `;

      document.body.appendChild(modal);

      // Manejar envío del formulario
      modal.querySelector('.quick-activity-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // Simular creación de actividad
        showToast('Actividad creada exitosamente', 'success');
        modal.remove();
        
        // Aquí se haría la llamada AJAX al servidor para crear la actividad real
        console.log('Creando actividad:', Object.fromEntries(formData));
      });
    }

    // Función para mostrar notificación de prueba
    function testNotification() {
      showToast('¡Notificación de prueba funcionando!', 'info');
    }

    // Añadir estilos para el modal
    const modalStyles = document.createElement('style');
    modalStyles.textContent = `
      .quick-activity-modal .modal-content {
        background: white;
        border-radius: 16px;
        padding: 24px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      }

      .quick-activity-modal .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #eee;
      }

      .quick-activity-modal .modal-header h3 {
        margin: 0;
        color: var(--gris);
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .quick-activity-modal .modal-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #666;
        padding: 8px;
        border-radius: 8px;
        transition: all 0.3s ease;
      }

      .quick-activity-modal .modal-close:hover {
        background: #f5f5f5;
        color: #333;
      }

      .quick-activity-modal .form-group {
        margin-bottom: 20px;
      }

      .quick-activity-modal .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: var(--gris);
      }

      .quick-activity-modal .form-group select,
      .quick-activity-modal .form-group textarea,
      .quick-activity-modal .form-group input {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
      }

      .quick-activity-modal .form-group select:focus,
      .quick-activity-modal .form-group textarea:focus,
      .quick-activity-modal .form-group input:focus {
        outline: none;
        border-color: var(--verde);
        box-shadow: 0 0 0 3px rgba(107, 112, 92, 0.1);
      }

      .quick-activity-modal .form-group textarea {
        resize: vertical;
        min-height: 80px;
      }

      .quick-activity-modal .form-actions {
        display: flex;
        gap: 12px;
        justify-content: flex-end;
        margin-top: 24px;
      }

      .quick-activity-modal .btn-cancel {
        padding: 12px 24px;
        border: 1px solid #ddd;
        background: white;
        color: #666;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
      }

      .quick-activity-modal .btn-cancel:hover {
        background: #f5f5f5;
        border-color: #ccc;
      }

      .quick-activity-modal .btn-create {
        padding: 12px 24px;
        border: none;
        background: linear-gradient(135deg, var(--verde), var(--beige-claro));
        color: white;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
      }

      .quick-activity-modal .btn-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(107, 112, 92, 0.3);
      }
    `;
    document.head.appendChild(modalStyles);
    

  </script>
</body>
</html>