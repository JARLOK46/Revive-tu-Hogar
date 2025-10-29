<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('admin');
require_once __DIR__.'/../../app/config/db.php';

$allowed = ['dashboard','pedidos','planes','clientes','usuarios','empleados','actividades','galeria','consultas','chatia'];
$reqEntity = $_GET['entity'] ?? 'dashboard';
$entity = in_array($reqEntity, $allowed, true) ? $reqEntity : 'dashboard';

// CSRF
if (!isset($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$flash_error = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$flash_success = $_SESSION['flash_success'] ?? null; unset($_SESSION['flash_success']);

if (!($pdo instanceof PDO)) {
  http_response_code(500);
  exit('Error de conexión a la base de datos.');
}

function redirect_entity(string $e){ header('Location: /admin/index.php?entity='.$e); exit; }

// Helpers de validación
function post_csrf_ok(): bool {
  $t = $_POST['csrf'] ?? ''; return $t && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}

function clean_str($v){ return trim((string)$v); }
function clean_int($v){ return (int)$v; }
function clean_float($v){ return (float)$v; }

// Acciones CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!post_csrf_ok()) { $_SESSION['flash_error'] = 'CSRF inválido.'; redirect_entity($entity); }

  try {
    switch ($entity) {
      case 'planes':
        if ($_POST['action']==='create') {
          $st=$pdo->prepare('INSERT INTO planes (nombre_plan, descripcion, precio, duracion_dias) VALUES (?,?,?,?)');
          $st->execute([ clean_str($_POST['nombre_plan']??''), clean_str($_POST['descripcion']??''), clean_float($_POST['precio']??0), clean_int($_POST['duracion_dias']??0) ]);
          $_SESSION['flash_success']='Plan creado.';
        } elseif ($_POST['action']==='update') {
          $st=$pdo->prepare('UPDATE planes SET nombre_plan=?, descripcion=?, precio=?, duracion_dias=? WHERE id=?');
          $st->execute([ clean_str($_POST['nombre_plan']??''), clean_str($_POST['descripcion']??''), clean_float($_POST['precio']??0), clean_int($_POST['duracion_dias']??0), clean_int($_POST['id']??0) ]);
          $_SESSION['flash_success']='Plan actualizado.';
        } elseif ($_POST['action']==='delete') {
          $id=clean_int($_POST['id']??0);
          $c=$pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE plan_id=?'); $c->execute([$id]);
          if ((int)$c->fetchColumn()>0){ $_SESSION['flash_error']='No se puede eliminar: plan asignado a pedidos.'; }
          else { $st=$pdo->prepare('DELETE FROM planes WHERE id=?'); $st->execute([$id]); $_SESSION['flash_success']='Plan eliminado.'; }
        }
        break;

      case 'clientes':
        if ($_POST['action']==='create') {
          $st=$pdo->prepare('INSERT INTO clientes (nombre,apellido,correo,telefono,direccion,fecha_registro,usuario_id) VALUES (?,?,?,?,?,NOW(),?)');
          $st->execute([ clean_str($_POST['nombre']??''), clean_str($_POST['apellido']??''), clean_str($_POST['correo']??''), clean_str($_POST['telefono']??''), clean_str($_POST['direccion']??''), clean_int($_POST['usuario_id']??0) ]);
          $_SESSION['flash_success']='Cliente creado.';
        } elseif ($_POST['action']==='update') {
          $st=$pdo->prepare('UPDATE clientes SET nombre=?, apellido=?, correo=?, telefono=?, direccion=?, usuario_id=? WHERE id=?');
          $st->execute([ clean_str($_POST['nombre']??''), clean_str($_POST['apellido']??''), clean_str($_POST['correo']??''), clean_str($_POST['telefono']??''), clean_str($_POST['direccion']??''), clean_int($_POST['usuario_id']??0), clean_int($_POST['id']??0) ]);
          $_SESSION['flash_success']='Cliente actualizado.';
        } elseif ($_POST['action']==='delete') {
          $id=clean_int($_POST['id']??0);
          $c=$pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE cliente_id=?'); $c->execute([$id]);
          if ((int)$c->fetchColumn()>0){ $_SESSION['flash_error']='No se puede eliminar: cliente con pedidos.'; }
          else { $st=$pdo->prepare('DELETE FROM clientes WHERE id=?'); $st->execute([$id]); $_SESSION['flash_success']='Cliente eliminado.'; }
        }
        break;

      case 'usuarios':
        if ($_POST['action']==='create') {
          $hash = password_hash((string)($_POST['contrasena']??''), PASSWORD_DEFAULT);
          $st=$pdo->prepare('INSERT INTO usuarios (nombre_usuario, contrasena_hash, rol, correo_electronico) VALUES (?,?,?,?)');
          $st->execute([ clean_str($_POST['nombre_usuario']??''), $hash, clean_str($_POST['rol']??'cliente'), clean_str($_POST['correo_electronico']??'') ]);
          $_SESSION['flash_success']='Usuario creado.';
        } elseif ($_POST['action']==='update') {
          $id=clean_int($_POST['id']??0);
          $sql='UPDATE usuarios SET nombre_usuario=?, rol=?, correo_electronico=?';
          $params=[ clean_str($_POST['nombre_usuario']??''), clean_str($_POST['rol']??'cliente'), clean_str($_POST['correo_electronico']??'') ];
          $pwd=trim((string)($_POST['contrasena']??''));
          if ($pwd!==''){ $sql.=', contrasena_hash=?'; $params[] = password_hash($pwd,PASSWORD_DEFAULT); }
          $sql.=' WHERE id=?'; $params[]=$id;
          $st=$pdo->prepare($sql); $st->execute($params);
          $_SESSION['flash_success']='Usuario actualizado.';
        } elseif ($_POST['action']==='delete') {
          $id=clean_int($_POST['id']??0);
          $c1=$pdo->prepare('SELECT COUNT(*) FROM clientes WHERE usuario_id=?'); $c1->execute([$id]);
          $c2=$pdo->prepare('SELECT COUNT(*) FROM empleados WHERE usuario_id=?'); $c2->execute([$id]);
          if ((int)$c1->fetchColumn()>0 || (int)$c2->fetchColumn()>0){ $_SESSION['flash_error']='No se puede eliminar: usuario asociado a cliente/empleado.'; }
          else { $st=$pdo->prepare('DELETE FROM usuarios WHERE id=?'); $st->execute([$id]); $_SESSION['flash_success']='Usuario eliminado.'; }
        }
        break;

      case 'empleados':
        if ($_POST['action']==='create') {
          // Crear usuario (rol empleado) y empleado enlazado
          $nombre = clean_str($_POST['nombre']??'');
          $telefono = clean_str($_POST['telefono']??'');
          $correo = clean_str($_POST['correo']??'');
          $pwd = (string)($_POST['contrasena']??'');
          if ($nombre===''){ $_SESSION['flash_error']='Nombre requerido.'; redirect_entity($entity); }
          if ($correo===''){ $_SESSION['flash_error']='Correo requerido.'; redirect_entity($entity); }
          if ($pwd===''){ $_SESSION['flash_error']='Contraseña requerida.'; redirect_entity($entity); }
          $pdo->beginTransaction();
          try {
            // Generar nombre de usuario a partir del correo
            $baseUser = $correo!=='' ? preg_replace('/[^a-z0-9_\.\-]/i','', explode('@',$correo)[0] ?: 'empleado') : 'empleado';
            $username = $baseUser; $i=1;
            $chk = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE nombre_usuario = ?');
            while (true) { $chk->execute([$username]); if ((int)$chk->fetchColumn()===0) break; $username = $baseUser.$i; $i++; }
            $hash = password_hash($pwd, PASSWORD_DEFAULT);
            $insU = $pdo->prepare('INSERT INTO usuarios (nombre_usuario, contrasena_hash, rol, correo_electronico) VALUES (?,?,"empleado",?)');
            $insU->execute([$username, $hash, $correo]);
            $uid = (int)$pdo->lastInsertId();
            $insE = $pdo->prepare('INSERT INTO empleados (nombre, cargo, telefono, correo, fecha_contratacion, usuario_id) VALUES (?,?,?,?,CURDATE(),?)');
            $insE->execute([$nombre, 'empleado', $telefono, $correo, $uid]);
            $pdo->commit();
            $_SESSION['flash_success']='Empleado creado.';
          } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error']='No se pudo crear el empleado.';
          }
        } elseif ($_POST['action']==='update') {
          $st=$pdo->prepare('UPDATE empleados SET nombre=?, telefono=?, correo=? WHERE id=?');
          $st->execute([ clean_str($_POST['nombre']??''), clean_str($_POST['telefono']??''), clean_str($_POST['correo']??''), clean_int($_POST['id']??0) ]);
          $_SESSION['flash_success']='Empleado actualizado.';
        } elseif ($_POST['action']==='delete') {
          $id=clean_int($_POST['id']??0);
          $c=$pdo->prepare('SELECT COUNT(*) FROM pedidos WHERE empleado_id=?'); $c->execute([$id]);
          if ((int)$c->fetchColumn()>0){ $_SESSION['flash_error']='No se puede eliminar: empleado asignado a pedidos.'; }
          else { $st=$pdo->prepare('DELETE FROM empleados WHERE id=?'); $st->execute([$id]); $_SESSION['flash_success']='Empleado eliminado.'; }
        }
        break;

      case 'pedidos':
        if ($_POST['action']==='create') {
          $st=$pdo->prepare('INSERT INTO pedidos (cliente_id,plan_id,empleado_id,fecha,total,estado) VALUES (?,?,?,?,?,?)');
          $st->execute([ clean_int($_POST['cliente_id']??0), clean_int($_POST['plan_id']??0), ($_POST['empleado_id']!=='')?clean_int($_POST['empleado_id']):null, date('Y-m-d H:i:s'), clean_float($_POST['total']??0), clean_str($_POST['estado']??'pendiente') ]);
          $_SESSION['flash_success']='Pedido creado.';
        } elseif ($_POST['action']==='update') {
          $st=$pdo->prepare('UPDATE pedidos SET cliente_id=?, plan_id=?, empleado_id=?, total=?, estado=? WHERE id=?');
          $st->execute([ clean_int($_POST['cliente_id']??0), clean_int($_POST['plan_id']??0), ($_POST['empleado_id']!=='')?clean_int($_POST['empleado_id']):null, clean_float($_POST['total']??0), clean_str($_POST['estado']??'pendiente'), clean_int($_POST['id']??0) ]);
          $_SESSION['flash_success']='Pedido actualizado.';
        } elseif ($_POST['action']==='delete') {
          $id=clean_int($_POST['id']??0);
          $pdo->beginTransaction();
          $pdo->prepare('DELETE FROM facturas WHERE pedido_id=?')->execute([$id]);
          $pdo->prepare('DELETE FROM detallespedidos WHERE pedido_id=?')->execute([$id]);
          $pdo->prepare('DELETE FROM pedidos WHERE id=?')->execute([$id]);
          $pdo->commit();
          $_SESSION['flash_success']='Pedido eliminado.';
        }
        break;

      case 'actividades':
        if ($_POST['action']==='create') {
          $tipo = clean_str($_POST['tipo'] ?? 'nota');
          $descripcion = clean_str($_POST['descripcion'] ?? '');
          $cliente_id = clean_int($_POST['cliente_id'] ?? 0);
          $pedido_id = ($_POST['pedido_id'] !== '') ? clean_int($_POST['pedido_id']) : null;
          $empleado_id = clean_int($_POST['empleado_id'] ?? 0);
          $fecha_programada = ($_POST['fecha_programada'] !== '') ? $_POST['fecha_programada'] : null;
          
          if ($descripcion === '') { $_SESSION['flash_error'] = 'Descripción requerida.'; redirect_entity($entity); }
          
          // Crear tabla de actividades si no existe
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
          
          $st = $pdo->prepare('INSERT INTO actividades (tipo, descripcion, cliente_id, pedido_id, empleado_id, fecha_programada) VALUES (?,?,?,?,?,?)');
          $st->execute([$tipo, $descripcion, $cliente_id, $pedido_id, $empleado_id, $fecha_programada]);
          $_SESSION['flash_success'] = 'Actividad registrada.';
        } elseif ($_POST['action']==='update') {
          $id = clean_int($_POST['id'] ?? 0);
          $estado = clean_str($_POST['estado'] ?? 'pendiente');
          $resultado = clean_str($_POST['resultado'] ?? '');
          $prioridad = clean_str($_POST['prioridad'] ?? 'media');
          
          $st = $pdo->prepare('UPDATE actividades SET estado=?, resultado=?, prioridad=? WHERE id=?');
          $st->execute([$estado, $resultado, $prioridad, $id]);
          $_SESSION['flash_success'] = 'Actividad actualizada.';
        } elseif ($_POST['action']==='delete') {
          $id = clean_int($_POST['id'] ?? 0);
          $st = $pdo->prepare('DELETE FROM actividades WHERE id=?');
          $st->execute([$id]);
          $_SESSION['flash_success'] = 'Actividad eliminada.';
        }
        break;

      case 'galeria':
        if ($_POST['action']==='upload') {
          $titulo = clean_str($_POST['titulo'] ?? '');
          $descripcion = clean_str($_POST['descripcion'] ?? '');
          $cliente_id = isset($_POST['cliente_id']) && $_POST['cliente_id']!=='' ? (int)$_POST['cliente_id'] : null;
          
          if (!$titulo) { 
            $_SESSION['flash_error'] = 'Título requerido'; 
            redirect_entity($entity); 
          }
          
          if (!isset($_FILES['imagen']) || $_FILES['imagen']['error'] !== UPLOAD_ERR_OK) {
            $errors = [
              UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize de PHP',
              UPLOAD_ERR_FORM_SIZE => 'El archivo excede MAX_FILE_SIZE del formulario',
              UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
              UPLOAD_ERR_NO_FILE => 'No se envió archivo',
              UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
              UPLOAD_ERR_CANT_WRITE => 'No se pudo escribir en disco',
              UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la subida'
            ];
            $err = $_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg = $errors[$err] ?? "Error desconocido: $err";
            $_SESSION['flash_error'] = $msg;
            redirect_entity($entity);
          }
          
          $f = $_FILES['imagen'];
          $maxSize = 5 * 1024 * 1024; // 5MB
          if ($f['size'] > $maxSize) { 
            $_SESSION['flash_error'] = "El archivo excede 5MB. Tamaño: " . round($f['size']/1024/1024, 2) . "MB";
            redirect_entity($entity);
          }
          
          // Validación MIME
          $mime = null;
          if (function_exists('finfo_file')) {
            $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']);
          } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($f['tmp_name']);
          }
          
          $allowedMime = ['image/jpeg','image/png','image/webp'];
          if ($mime && !in_array($mime, $allowedMime, true)) { 
            $_SESSION['flash_error'] = "Tipo MIME no permitido: $mime";
            redirect_entity($entity);
          }
          
          $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
          $allowed = ['jpg','jpeg','png','webp'];
          if (!in_array($ext, $allowed, true)) { 
            $_SESSION['flash_error'] = "Formato no soportado: $ext";
            redirect_entity($entity);
          }
          
          // Crear directorio si no existe
          $destDir = __DIR__ . '/../uploads/proyectos';
          if (!is_dir($destDir)) { 
            if (!mkdir($destDir, 0777, true)) {
              $_SESSION['flash_error'] = "No se pudo crear el directorio: $destDir";
              redirect_entity($entity);
            }
          }
          
          // Verificar permisos
          if (!is_writable($destDir)) {
            $_SESSION['flash_error'] = "El directorio no tiene permisos de escritura: $destDir";
            redirect_entity($entity);
          }
          
          // Guardar archivo
          $filename = uniqid('proj_', true) . '.' . $ext;
          $dest = $destDir . '/' . $filename;
          
          if (!move_uploaded_file($f['tmp_name'], $dest)) { 
            $_SESSION['flash_error'] = "No se pudo mover el archivo a: $dest";
            redirect_entity($entity);
          }
          
          // Insertar en BD
          $imagen_url = '/uploads/proyectos/' . $filename;
          $st = $pdo->prepare('INSERT INTO proyectos_galeria (titulo, descripcion, imagen_url, cliente_id) VALUES (?, ?, ?, ?)');
          $result = $st->execute([$titulo, $descripcion, $imagen_url, $cliente_id]);
          
          if (!$result) {
            $_SESSION['flash_error'] = 'Error al insertar en la base de datos';
            redirect_entity($entity);
          }
          
          $_SESSION['flash_success'] = 'Imagen subida correctamente';
        } elseif ($_POST['action']==='delete_galeria') {
          $id = (int)($_POST['id'] ?? 0);
          $q = $pdo->prepare('SELECT imagen_url FROM proyectos_galeria WHERE id=?');
          $q->execute([$id]);
          $img = (string)($q->fetchColumn() ?: '');
          $pdo->prepare('DELETE FROM proyectos_galeria WHERE id=?')->execute([$id]);
          if ($img) {
            $path = __DIR__ . '/../' . ltrim($img, '/');
            if (is_file($path) && file_exists($path)) { @unlink($path); }
          }
          $_SESSION['flash_success'] = 'Elemento eliminado';
        } elseif ($_POST['action']==='import_galeria') {
          $dir = __DIR__ . '/../uploads/proyectos';
          if (is_dir($dir)) {
            $patterns = ['*.jpg','*.jpeg','*.png','*.webp'];
            $files = [];
            foreach ($patterns as $pat) {
              foreach (glob($dir.'/'.$pat, GLOB_BRACE) ?: [] as $p) { $files[] = $p; }
            }
            $inserted = 0;
            foreach ($files as $p) {
              $rel = '/uploads/proyectos/'.basename($p);
              $q = $pdo->prepare('SELECT COUNT(*) FROM proyectos_galeria WHERE imagen_url=?');
              $q->execute([$rel]);
              if ((int)$q->fetchColumn() === 0) {
                $titulo = ucwords(str_replace(['-','_'],' ', pathinfo($p, PATHINFO_FILENAME)));
                $st = $pdo->prepare('INSERT INTO proyectos_galeria (titulo, descripcion, imagen_url, cliente_id) VALUES (?, ?, ?, NULL)');
                $st->execute([$titulo, '', $rel]);
                $inserted++;
              }
            }
            $_SESSION['flash_success'] = $inserted>0 ? ("Importadas ${inserted} imágenes desde carpeta") : 'No hay nuevas imágenes para importar.';
          } else {
            $_SESSION['flash_error'] = 'Carpeta de proyectos no encontrada';
          }
        }
        break;

      case 'consultas':
        if ($_POST['action']==='update') {
          $id = clean_int($_POST['id'] ?? 0);
          $respuesta = clean_str($_POST['respuesta'] ?? '');
          $estado = clean_str($_POST['estado'] ?? 'pendiente');
          
          if ($respuesta === '' && $estado === 'respondida') {
            $_SESSION['flash_error'] = 'La respuesta es requerida para marcar como respondida.';
            redirect_entity($entity);
          }
          
          $st = $pdo->prepare('UPDATE consultas SET respuesta=?, estado=?, updated_at=NOW() WHERE id=?');
          $st->execute([$respuesta, $estado, $id]);
          $_SESSION['flash_success'] = 'Consulta actualizada.';
        } elseif ($_POST['action']==='delete') {
          $id = clean_int($_POST['id'] ?? 0);
          $st = $pdo->prepare('DELETE FROM consultas WHERE id=?');
          $st->execute([$id]);
          $_SESSION['flash_success'] = 'Consulta eliminada.';
        }
        break;
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $_SESSION['flash_error'] = 'Operación fallida.';
  }
  redirect_entity($entity);
}

// Datos para selects
$clientes = $pdo->query('SELECT id, COALESCE(NULLIF(CONCAT(nombre," ",apellido)," "), CONCAT("Cliente #",id)) AS nombre FROM clientes ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$planes   = $pdo->query('SELECT id, nombre_plan FROM planes ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$empleados= $pdo->query('SELECT id, nombre FROM empleados ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
$usuarios = $pdo->query('SELECT id, nombre_usuario FROM usuarios ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);

// KPIs avanzados para dashboard CRM
$k_total_clientes = 0; $k_total_pedidos = 0; $k_pendientes = 0; $k_ingresos = 0.0; $ultimos_pedidos = []; $datos_graficos = [];
$k_nuevos_clientes_mes = 0; $k_tasa_conversion = 0; $k_valor_promedio = 0; $k_ingresos_mes = 0;
$k_empleado_top = ''; $k_plan_popular = ''; $k_alertas = []; $k_tendencia_ingresos = 0;
if ($entity==='dashboard') {
  // KPIs básicos
  $k_total_clientes = (int)$pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
  $k_total_pedidos  = (int)$pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();
  $k_pendientes     = (int)$pdo->query('SELECT COUNT(*) FROM pedidos WHERE estado="pendiente"')->fetchColumn();
  $k_ingresos       = (float)$pdo->query('SELECT COALESCE(SUM(total),0) FROM pedidos')->fetchColumn();
  
  // KPIs avanzados CRM
  $k_nuevos_clientes_mes = (int)$pdo->query('SELECT COUNT(*) FROM clientes WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
  $k_ingresos_mes = (float)$pdo->query('SELECT COALESCE(SUM(total),0) FROM pedidos WHERE fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
  $k_valor_promedio = $k_total_pedidos > 0 ? round($k_ingresos / $k_total_pedidos, 2) : 0;
  $k_tasa_conversion = $k_total_clientes > 0 ? round(($k_total_pedidos / $k_total_clientes) * 100, 1) : 0;
  
  // Empleado top del mes
  $emp_top = $pdo->query('SELECT e.nombre, COUNT(p.id) as pedidos FROM empleados e LEFT JOIN pedidos p ON p.empleado_id = e.id WHERE p.fecha >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY e.id ORDER BY pedidos DESC LIMIT 1')->fetch();
  $k_empleado_top = $emp_top ? $emp_top['nombre'] . ' (' . $emp_top['pedidos'] . ' pedidos)' : 'N/A';
  
  // Plan más popular
  $plan_pop = $pdo->query('SELECT pl.nombre_plan, COUNT(p.id) as pedidos FROM planes pl LEFT JOIN pedidos p ON p.plan_id = pl.id GROUP BY pl.id ORDER BY pedidos DESC LIMIT 1')->fetch();
  $k_plan_popular = $plan_pop ? $plan_pop['nombre_plan'] . ' (' . $plan_pop['pedidos'] . ' pedidos)' : 'N/A';
  
  // Tendencia de ingresos (comparación mes actual vs anterior)
  $ingresos_mes_anterior = (float)$pdo->query('SELECT COALESCE(SUM(total),0) FROM pedidos WHERE fecha >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND fecha < DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
  $k_tendencia_ingresos = $ingresos_mes_anterior > 0 ? round((($k_ingresos_mes - $ingresos_mes_anterior) / $ingresos_mes_anterior) * 100, 1) : 0;
  
  // Alertas inteligentes
  $pedidos_sin_empleado = (int)$pdo->query('SELECT COUNT(*) FROM pedidos WHERE empleado_id IS NULL AND estado = "pendiente"')->fetchColumn();
  if ($pedidos_sin_empleado > 0) $k_alertas[] = "⚠️ $pedidos_sin_empleado pedidos sin asignar";
  
  $facturas_pendientes = (int)$pdo->query('SELECT COUNT(*) FROM facturas WHERE estado_pago = "pendiente" AND fecha_factura < DATE_SUB(NOW(), INTERVAL 7 DAY)')->fetchColumn();
  if ($facturas_pendientes > 0) $k_alertas[] = "💰 $facturas_pendientes facturas vencidas";
  
  $clientes_sin_pedidos = (int)$pdo->query('SELECT COUNT(*) FROM clientes c WHERE NOT EXISTS (SELECT 1 FROM pedidos p WHERE p.cliente_id = c.id) AND c.fecha_registro < DATE_SUB(NOW(), INTERVAL 30 DAY)')->fetchColumn();
  if ($clientes_sin_pedidos > 0) $k_alertas[] = "👥 $clientes_sin_pedidos clientes inactivos";
  
  // Paginación para tabla de actividad reciente
  $filas_por_pagina = isset($_GET['filas']) ? (int)$_GET['filas'] : 5;
  if ($filas_por_pagina < 1 || $filas_por_pagina > 50) $filas_por_pagina = 5;
  
  $stmt = $pdo->prepare('SELECT p.id, COALESCE(NULLIF(CONCAT(c.nombre," ",c.apellido)," "), CONCAT("Cliente #",c.id)) AS cliente, pl.nombre_plan, p.total, p.estado, p.fecha FROM pedidos p LEFT JOIN clientes c ON c.id=p.cliente_id LEFT JOIN planes pl ON pl.id=p.plan_id ORDER BY p.id DESC LIMIT ?');
  $stmt->execute([$filas_por_pagina]);
  $ultimos_pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Datos para gráfico lineal - Pedidos e ingresos por mes (últimos 6 meses)
  $stmt = $pdo->prepare("SELECT 
                          DATE_FORMAT(fecha, '%Y-%m') as periodo,
                          COUNT(*) as total_pedidos,
                          SUM(total) as ingresos_mes
                         FROM pedidos 
                         WHERE fecha >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         GROUP BY DATE_FORMAT(fecha, '%Y-%m')
                         ORDER BY periodo");
  $stmt->execute();
  $datos_graficos['lineal_mensual'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Datos para gráfico lineal - Pedidos e ingresos por semana (últimas 8 semanas)
  $stmt = $pdo->prepare("SELECT 
                          CONCAT(YEAR(fecha), '-S', WEEK(fecha, 1)) as periodo,
                          COUNT(*) as total_pedidos,
                          SUM(total) as ingresos_mes
                         FROM pedidos 
                         WHERE fecha >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
                         GROUP BY YEAR(fecha), WEEK(fecha, 1)
                         ORDER BY YEAR(fecha), WEEK(fecha, 1)");
  $stmt->execute();
  $datos_graficos['lineal_semanal'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Datos para gráfico lineal - Pedidos e ingresos por día (últimos 7 días)
  $stmt = $pdo->prepare("SELECT 
                          DATE_FORMAT(fecha, '%Y-%m-%d') as periodo,
                          CASE DAYOFWEEK(fecha)
                              WHEN 1 THEN 'Domingo'
                              WHEN 2 THEN 'Lunes'
                              WHEN 3 THEN 'Martes'
                              WHEN 4 THEN 'Miércoles'
                              WHEN 5 THEN 'Jueves'
                              WHEN 6 THEN 'Viernes'
                              WHEN 7 THEN 'Sábado'
                          END as dia_nombre,
                          COUNT(*) as total_pedidos,
                          SUM(total) as ingresos_mes
                         FROM pedidos 
                         WHERE fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         GROUP BY DATE_FORMAT(fecha, '%Y-%m-%d'), DAYOFWEEK(fecha)
                         ORDER BY fecha");
  $stmt->execute();
  $result_diario = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Crear array con todos los días de la semana
  $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
  $lineal_diario = [];
  
  // Inicializar todos los días con 0
  for($i = 6; $i >= 0; $i--) {
      $fecha = date('Y-m-d', strtotime("-$i days"));
      $dia_semana = $dias_semana[date('N', strtotime($fecha)) - 1];
      $lineal_diario[] = [
          'periodo' => $fecha,
          'dia_nombre' => $dia_semana,
          'total_pedidos' => 0,
          'ingresos_mes' => 0
      ];
  }
  
  // Actualizar con datos reales
  foreach($result_diario as $row) {
      foreach($lineal_diario as &$dia) {
          if($dia['periodo'] == $row['periodo']) {
              $dia['total_pedidos'] = $row['total_pedidos'];
              $dia['ingresos_mes'] = $row['ingresos_mes'];
              break;
          }
      }
  }
  
  $datos_graficos['lineal_diario'] = $lineal_diario;
  
  // Datos para gráfico de torta - Porcentaje de compra por plan
  $stmt = $pdo->prepare("SELECT 
                          pl.nombre_plan as plan,
                          COUNT(p.id) as cantidad,
                          ROUND((COUNT(p.id) * 100.0 / (SELECT COUNT(*) FROM pedidos)), 2) as porcentaje
                         FROM pedidos p
                         LEFT JOIN planes pl ON p.plan_id = pl.id
                         GROUP BY p.plan_id, pl.nombre_plan
                         ORDER BY cantidad DESC");
  $stmt->execute();
  $datos_graficos['torta'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Datos para gráfico de barras - Ingresos por plan
  $stmt = $pdo->prepare("SELECT 
                          pl.nombre_plan,
                          COUNT(p.id) as total_pedidos,
                          SUM(p.total) as ingresos_total
                         FROM pedidos p
                         LEFT JOIN planes pl ON p.plan_id = pl.id
                         GROUP BY p.plan_id, pl.nombre_plan
                         ORDER BY ingresos_total DESC");
  $stmt->execute();
  $datos_graficos['barras'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Datos listados
function list_rows(PDO $pdo, string $entity){
  switch ($entity){
    case 'planes':   return $pdo->query('SELECT * FROM planes ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    case 'clientes': return $pdo->query('SELECT * FROM clientes ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    case 'usuarios': return $pdo->query('SELECT id,nombre_usuario,rol,correo_electronico,fecha_registro FROM usuarios ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    case 'empleados':return $pdo->query('SELECT e.*, COALESCE(SUM(f.estado_pago="pagado"),0) AS pagos_exitosos FROM empleados e LEFT JOIN pedidos p ON p.empleado_id=e.id LEFT JOIN facturas f ON f.pedido_id=p.id GROUP BY e.id ORDER BY e.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    case 'pedidos':  return $pdo->query('SELECT p.*, pl.nombre_plan, c.nombre AS cliente_nombre, e.nombre AS empleado_nombre FROM pedidos p LEFT JOIN planes pl ON pl.id=p.plan_id LEFT JOIN clientes c ON c.id=p.cliente_id LEFT JOIN empleados e ON e.id=p.empleado_id ORDER BY p.id DESC')->fetchAll(PDO::FETCH_ASSOC);
    case 'actividades': 
      // Crear tabla si no existe
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
      return $pdo->query('SELECT a.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, e.nombre AS empleado_nombre, p.id AS pedido_numero FROM actividades a LEFT JOIN clientes c ON c.id=a.cliente_id LEFT JOIN empleados e ON e.id=a.empleado_id LEFT JOIN pedidos p ON p.id=a.pedido_id ORDER BY a.fecha_creacion DESC')->fetchAll(PDO::FETCH_ASSOC);
    case 'consultas':
      return $pdo->query('SELECT c.*, u.nombre_usuario, u.correo_electronico FROM consultas c LEFT JOIN usuarios u ON u.id=c.usuario_id ORDER BY c.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
  }
  return [];
}
$rows = list_rows($pdo,$entity);

function sel($a,$b){ return (string)$a===(string)$b ? 'selected' : ''; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin · <?php echo htmlspecialchars(strtoupper($entity)); ?></title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="../assets/js/admin.js"></script>
  <style>
    /* Sidebar moderno */
    .sidebar {
      position: fixed;
      left: 0;
      top: 0;
      width: 280px;
      height: 100vh;
      background: linear-gradient(180deg, #FAF9F6 0%, #F5EFE6 50%, #E6D8C3 100%);
      box-shadow: 4px 0 20px rgba(140,106,79,0.15);
      border-right: 1px solid rgba(140,106,79,0.2);
      z-index: 1000;
      transition: all 0.3s ease;
      display: flex;
      flex-direction: column;
    }

    .sidebar-header {
      padding: 25px 20px;
      border-bottom: 1px solid rgba(140,106,79,0.2);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 12px;
      color: #2C2C2C;
      font-size: 1.4rem;
      font-weight: 700;
    }

    .logo i {
      color: #8C6A4F;
      font-size: 1.6rem;
    }

    .sidebar-toggle {
      background: none;
      border: none;
      color: #fff;
      font-size: 1.2rem;
      cursor: pointer;
      padding: 8px;
      border-radius: 6px;
      transition: all 0.3s ease;
      display: none;
    }

    .sidebar-toggle:hover {
      background: rgba(255,255,255,0.1);
    }

    .sidebar-nav {
      flex: 1;
      padding: 20px 0;
      overflow-y: auto;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px 25px;
      color: #6B705C;
      text-decoration: none;
      transition: all 0.3s ease;
      position: relative;
      border-left: 3px solid transparent;
    }

    .nav-item:hover {
      background: rgba(140,106,79,0.1);
      color: #2C2C2C;
      border-left-color: #8C6A4F;
    }

    .nav-item.active {
      background: linear-gradient(90deg, rgba(140,106,79,0.15) 0%, rgba(140,106,79,0.05) 100%);
      color: #8C6A4F;
      border-left-color: #8C6A4F;
      font-weight: 600;
    }

    .nav-item i {
      font-size: 1.1rem;
      width: 20px;
      text-align: center;
      transition: all 0.3s ease;
      color: #6B705C;
    }

    .nav-item:hover i {
      color: #8C6A4F;
      transform: scale(1.1);
    }

    .nav-item.active i {
      color: #8C6A4F;
    }

    .nav-text {
      font-size: 0.95rem;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .nav-item:hover .nav-text {
      color: #2C2C2C;
      transform: translateX(5px);
    }

    .active-indicator {
      position: absolute;
      right: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 4px;
      height: 30px;
      background: #8C6A4F;
      border-radius: 2px 0 0 2px;
    }

    .sidebar-footer {
      padding: 20px;
      border-top: 1px solid rgba(140,106,79,0.2);
    }

    .footer-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 15px;
      color: #6B705C;
      text-decoration: none;
      border-radius: 8px;
      transition: all 0.3s ease;
      margin-bottom: 8px;
    }

    .footer-link:hover {
      background: rgba(140,106,79,0.1);
      color: #2C2C2C;
    }

    .footer-link.logout:hover {
      background: rgba(231,76,60,0.1);
      color: #e74c3c;
    }

    .footer-link i {
      font-size: 1rem;
      width: 16px;
      text-align: center;
    }

    .footer-link span {
      font-size: 0.9rem;
    }

    /* Layout principal */
    .main-content {
      margin-left: 280px;
      min-height: 100vh;
      background: #f8f9fa;
      transition: all 0.3s ease;
    }

    body {
      margin: 0;
      padding: 0;
      background: #f8f9fa;
    }

    .container.section {
      padding: 30px;
      max-width: none;
    }

    /* Responsividad para dispositivos móviles */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
        z-index: 1000;
      }

      .sidebar.active {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0;
      }

      .sidebar-toggle {
        display: block;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1001;
        background: #2c3e50;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 5px;
        cursor: pointer;
      }

      .container.section {
        padding: 20px 15px;
      }

      .kpis {
        grid-template-columns: 1fr;
      }

      .cards {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 480px) {
      .sidebar {
        width: 100%;
      }

      .container.section {
        padding: 15px 10px;
      }
    }

    .adm-grid{display:grid;gap:12px}
    table.table td form{display:inline}
    table.table .row-form{background:transparent;padding:8px;border:1px solid #e8e3d3;border-radius:8px;margin:0;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    table.table .row-form::before{display:none}
    table.table .row-form .input{min-width:120px;padding:8px 12px;font-size:0.9rem}

    /* Estilos modernos para Chat IA n8n */
    #n8n-chat-admin {
      height: 75vh;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 
        0 20px 60px rgba(140, 106, 79, 0.15),
        0 8px 25px rgba(140, 106, 79, 0.1),
        inset 0 1px 0 rgba(255, 255, 255, 0.8);
      background: linear-gradient(145deg, #faf9f6 0%, #f5f2ea 100%);
      border: 2px solid rgba(140, 106, 79, 0.1);
      position: relative;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Efectos de glassmorphism para el contenedor */
    #n8n-chat-admin::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(
        135deg,
        rgba(255, 255, 255, 0.1) 0%,
        rgba(255, 255, 255, 0.05) 50%,
        rgba(140, 106, 79, 0.05) 100%
      );
      pointer-events: none;
      z-index: 1;
    }

    /* Personalización del header del chat n8n */
    #n8n-chat-admin .n8n-chat-header {
      background: linear-gradient(135deg, #8C6A4F 0%, #D2B48C 50%, #E6D8C3 100%) !important;
      padding: 25px 30px !important;
      border-bottom: 3px solid rgba(140, 106, 79, 0.2) !important;
      box-shadow: 0 4px 20px rgba(140, 106, 79, 0.15) !important;
      position: relative;
    }

    #n8n-chat-admin .n8n-chat-header::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.5), transparent);
    }

    #n8n-chat-admin .n8n-chat-title {
      color: #fff !important;
      font-size: 1.4rem !important;
      font-weight: 700 !important;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
      letter-spacing: 0.5px !important;
    }

    #n8n-chat-admin .n8n-chat-subtitle {
      color: rgba(255, 255, 255, 0.9) !important;
      font-size: 0.95rem !important;
      margin-top: 5px !important;
      font-weight: 400 !important;
    }

    /* Área de mensajes mejorada */
    #n8n-chat-admin .n8n-chat-messages {
      background: linear-gradient(145deg, #faf9f6 0%, #f8f6f0 100%) !important;
      padding: 25px !important;
      position: relative;
    }

    #n8n-chat-admin .n8n-chat-messages::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 20px;
      background: linear-gradient(180deg, rgba(140, 106, 79, 0.05) 0%, transparent 100%);
      pointer-events: none;
    }

    /* Mensajes del bot mejorados */
    #n8n-chat-admin .n8n-chat-message.bot {
      background: linear-gradient(135deg, #fff 0%, #fafafa 100%) !important;
      border: 2px solid rgba(140, 106, 79, 0.1) !important;
      border-radius: 20px 20px 20px 5px !important;
      padding: 18px 22px !important;
      margin: 12px 0 !important;
      box-shadow: 
        0 8px 25px rgba(140, 106, 79, 0.1),
        0 3px 10px rgba(140, 106, 79, 0.05) !important;
      position: relative;
      max-width: 85% !important;
      animation: slideInLeft 0.4s ease-out;
    }

    #n8n-chat-admin .n8n-chat-message.bot::before {
      content: '';
      position: absolute;
      left: -8px;
      bottom: 8px;
      width: 0;
      height: 0;
      border-style: solid;
      border-width: 0 15px 15px 0;
      border-color: transparent #fff transparent transparent;
      filter: drop-shadow(-2px 2px 2px rgba(140, 106, 79, 0.1));
    }

    /* Mensajes del usuario mejorados */
    #n8n-chat-admin .n8n-chat-message.user {
      background: linear-gradient(135deg, #8C6A4F 0%, #A0826D 50%, #D2B48C 100%) !important;
      color: #fff !important;
      border: none !important;
      border-radius: 20px 20px 5px 20px !important;
      padding: 18px 22px !important;
      margin: 12px 0 !important;
      margin-left: auto !important;
      box-shadow: 
        0 8px 25px rgba(140, 106, 79, 0.25),
        0 3px 10px rgba(140, 106, 79, 0.15) !important;
      position: relative;
      max-width: 85% !important;
      animation: slideInRight 0.4s ease-out;
    }

    #n8n-chat-admin .n8n-chat-message.user::after {
      content: '';
      position: absolute;
      right: -8px;
      bottom: 8px;
      width: 0;
      height: 0;
      border-style: solid;
      border-width: 15px 15px 0 0;
      border-color: #8C6A4F transparent transparent transparent;
      filter: drop-shadow(2px 2px 2px rgba(140, 106, 79, 0.2));
    }

    /* Área de input mejorada */
    #n8n-chat-admin .n8n-chat-input-container {
      background: linear-gradient(135deg, #f5f2ea 0%, #e8e3d3 100%) !important;
      padding: 25px 30px !important;
      border-top: 3px solid rgba(140, 106, 79, 0.15) !important;
      position: relative;
    }

    #n8n-chat-admin .n8n-chat-input-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: linear-gradient(90deg, transparent, rgba(140, 106, 79, 0.3), transparent);
    }

    #n8n-chat-admin .n8n-chat-input {
      border: 2px solid rgba(140, 106, 79, 0.2) !important;
      border-radius: 25px !important;
      padding: 15px 25px !important;
      font-size: 1rem !important;
      background: #fff !important;
      box-shadow: 
        inset 0 2px 5px rgba(140, 106, 79, 0.05),
        0 4px 15px rgba(140, 106, 79, 0.1) !important;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }

    #n8n-chat-admin .n8n-chat-input:focus {
      border-color: #8C6A4F !important;
      box-shadow: 
        inset 0 2px 5px rgba(140, 106, 79, 0.1),
        0 0 0 4px rgba(140, 106, 79, 0.15),
        0 8px 25px rgba(140, 106, 79, 0.2) !important;
      transform: translateY(-2px) !important;
    }

    #n8n-chat-admin .n8n-chat-input::placeholder {
      color: rgba(140, 106, 79, 0.6) !important;
      font-style: italic !important;
    }

    /* Botón de envío mejorado */
    #n8n-chat-admin .n8n-chat-send-button {
      background: linear-gradient(135deg, #8C6A4F 0%, #A0826D 50%, #D2B48C 100%) !important;
      border: none !important;
      border-radius: 50% !important;
      width: 50px !important;
      height: 50px !important;
      box-shadow: 
        0 6px 20px rgba(140, 106, 79, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2) !important;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
      position: relative;
      overflow: hidden;
    }

    #n8n-chat-admin .n8n-chat-send-button::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.6s ease;
    }

    #n8n-chat-admin .n8n-chat-send-button:hover {
      transform: translateY(-3px) scale(1.05) !important;
      box-shadow: 
        0 12px 35px rgba(140, 106, 79, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
    }

    #n8n-chat-admin .n8n-chat-send-button:hover::before {
      left: 100%;
    }

    #n8n-chat-admin .n8n-chat-send-button:active {
      transform: translateY(-1px) scale(0.98) !important;
    }

    /* Indicador de escritura mejorado */
    #n8n-chat-admin .n8n-chat-typing {
      background: linear-gradient(135deg, #fff 0%, #fafafa 100%) !important;
      border: 2px solid rgba(140, 106, 79, 0.1) !important;
      border-radius: 20px 20px 20px 5px !important;
      padding: 15px 20px !important;
      margin: 12px 0 !important;
      box-shadow: 0 4px 15px rgba(140, 106, 79, 0.1) !important;
      animation: pulse 2s infinite;
    }

    /* Animaciones */
    @keyframes slideInLeft {
      from {
        opacity: 0;
        transform: translateX(-30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(30px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
        opacity: 1;
      }
      50% {
        transform: scale(1.02);
        opacity: 0.9;
      }
    }

    /* Scrollbar personalizado */
    #n8n-chat-admin .n8n-chat-messages::-webkit-scrollbar {
      width: 8px;
    }

    #n8n-chat-admin .n8n-chat-messages::-webkit-scrollbar-track {
      background: rgba(140, 106, 79, 0.1);
      border-radius: 10px;
    }

    #n8n-chat-admin .n8n-chat-messages::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, #8C6A4F, #D2B48C);
      border-radius: 10px;
      border: 2px solid rgba(255, 255, 255, 0.2);
    }

    #n8n-chat-admin .n8n-chat-messages::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(135deg, #A0826D, #E6D8C3);
    }

    /* Responsive design */
    @media (max-width: 768px) {
      #n8n-chat-admin {
        height: 70vh;
        border-radius: 15px;
      }
      
      #n8n-chat-admin .n8n-chat-header {
        padding: 20px !important;
      }
      
      #n8n-chat-admin .n8n-chat-messages {
        padding: 20px !important;
      }
      
      #n8n-chat-admin .n8n-chat-input-container {
        padding: 20px !important;
      }
      
      #n8n-chat-admin .n8n-chat-message.bot,
      #n8n-chat-admin .n8n-chat-message.user {
        max-width: 95% !important;
        padding: 15px 18px !important;
      }
    }
    table.table .row-form .btn{padding:8px 16px;font-size:0.85rem;text-transform:none;letter-spacing:normal}
    /* Mejoras visuales */
    .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:12px 0}
    .kpi{background:#fff;border:1px solid #eee;border-radius:12px;padding:14px;box-shadow:0 4px 10px rgba(0,0,0,.04)}
    .kpi .val{font-size:22px;font-weight:700}
    .kpi small{color:#777}
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
    .card{background:#fff;border:1px solid #eee;border-radius:12px;padding:16px;box-shadow:0 4px 10px rgba(0,0,0,.04)}
    
    /* Formularios modernos */
    .row-form{display:flex;gap:12px;flex-wrap:wrap;background:linear-gradient(135deg,#faf9f5 0%,#f5f2ea 100%);padding:20px;border-radius:16px;border:2px solid #e8e3d3;margin-bottom:25px;box-shadow:0 6px 20px rgba(0,0,0,.08);position:relative;overflow:hidden}
    .row-form::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--beige-osc),var(--beige-claro))}
    .row-form .input{min-width:160px;padding:12px 16px;border:2px solid #e8e3d3;border-radius:10px;background:#fff;font-size:0.95rem;color:var(--beige-osc);transition:all 0.3s ease;box-shadow:0 2px 8px rgba(0,0,0,.05)}
    .row-form .input:focus{border-color:var(--beige-osc);box-shadow:0 4px 15px rgba(139,115,85,.2);transform:translateY(-2px);outline:none}
    .row-form .input::placeholder{color:#a0958a;font-style:italic}
    .row-form select.input{cursor:pointer;background-image:url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5"><path fill="%23a0958a" d="M2 0L0 2h4zm0 5L0 3h4z"/></svg>');background-repeat:no-repeat;background-position:right 12px center;background-size:12px;padding-right:40px}
    .row-form .btn{background:linear-gradient(135deg,var(--beige-osc) 0%,#a0826d 50%,#8b7355 100%);color:#fff;border:none;padding:14px 28px;border-radius:12px;font-weight:700;font-size:0.95rem;cursor:pointer;transition:all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);box-shadow:0 6px 20px rgba(139,115,85,.35), inset 0 1px 0 rgba(255,255,255,.2);text-transform:uppercase;letter-spacing:0.8px;position:relative;overflow:hidden}
    .row-form .btn::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.3),transparent);transition:left 0.6s ease}
    .row-form .btn:hover{transform:translateY(-4px) scale(1.02);box-shadow:0 12px 30px rgba(139,115,85,.45), inset 0 1px 0 rgba(255,255,255,.3);background:linear-gradient(135deg,#8b7355 0%,#a0826d 50%,var(--beige-osc) 100%)}
    .row-form .btn:hover::before{left:100%}
    .row-form .btn:active{transform:translateY(-2px) scale(0.98);box-shadow:0 6px 20px rgba(139,115,85,.35);transition:all 0.1s ease}
    /* Tablas modernas para todas las pestañas */
    .table{width:100%;border-collapse:separate;border-spacing:0;border-radius:12px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,.08);margin-top:20px;background:#fff}
    .table thead{background:linear-gradient(135deg,var(--beige-osc) 0%,#8b7355 100%)}
    .table th{padding:16px 12px;text-align:left;color:#fff;font-weight:600;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.5px;border:none;position:relative}
    .table th::after{content:'';position:absolute;bottom:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.3),transparent)}
    .table td{padding:14px 12px;border:none;border-bottom:1px solid #f0f0f0;font-size:0.9rem;color:#333;vertical-align:middle;position:relative}
    .table td:first-child{font-weight:600;color:var(--beige-osc);background:linear-gradient(90deg,transparent,rgba(139,115,85,0.05),transparent)}
    .table td:first-child::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--beige-osc);opacity:0.6}
    .table tbody tr{background:#fff;transition:all 0.3s ease}
    .table tbody tr:hover{background:linear-gradient(135deg,#faf9f5 0%,#f8f6f0 100%);transform:scale(1.01);box-shadow:0 4px 15px rgba(0,0,0,.1)}
    .table tbody tr:last-child td{border-bottom:none}
    .table tbody tr:nth-child(even){background:#fafafa}
    .table tbody tr:nth-child(even):hover{background:linear-gradient(135deg,#f8f6f0 0%,#f5f2ea 100%)}
    
    /* Botones de acción en tablas */
    .table .btn{background:linear-gradient(135deg,var(--beige-claro) 0%,#d4c4a8 50%,var(--beige-osc) 100%);color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;transition:all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);margin:3px;text-transform:none;position:relative;overflow:hidden;box-shadow:0 3px 10px rgba(139,115,85,.25), inset 0 1px 0 rgba(255,255,255,.15)}
    .table .btn::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.25),transparent);transition:left 0.5s ease}
    .table .btn:hover{transform:translateY(-3px) scale(1.05);box-shadow:0 6px 18px rgba(139,115,85,.4), inset 0 1px 0 rgba(255,255,255,.25);background:linear-gradient(135deg,var(--beige-osc) 0%,#a0826d 50%,#8b7355 100%)}
    .table .btn:hover::before{left:100%}
    .table .btn.btn-danger{background:linear-gradient(135deg,#e17055 0%,#dc3545 50%,#d63031 100%);box-shadow:0 3px 10px rgba(220,53,69,.25), inset 0 1px 0 rgba(255,255,255,.15)}
    .table .btn.btn-danger:hover{background:linear-gradient(135deg,#d63031 0%,#c0392b 50%,#a93226 100%);box-shadow:0 6px 18px rgba(214,48,49,.4), inset 0 1px 0 rgba(255,255,255,.25);transform:translateY(-3px) scale(1.05)}
    .table .btn:active{transform:translateY(-1px) scale(0.95);transition:all 0.1s ease}
    /* Pills modernos para estados */
    .pill{display:inline-block;padding:6px 12px;border-radius:20px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;box-shadow:0 2px 6px rgba(0,0,0,.15);border:2px solid transparent;transition:all 0.3s ease}
    .pill:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.2)}
    .pill.pendiente{background:linear-gradient(135deg,#fff3cd 0%,#ffeaa7 100%);color:#664d03;border-color:#f1c40f}
    .pill.enviado{background:linear-gradient(135deg,#cfe2ff 0%,#74b9ff 100%);color:#084298;border-color:#0984e3}
    .pill.entregado{background:linear-gradient(135deg,#d1e7dd 0%,#00b894 100%);color:#0f5132;border-color:#00a085}
    .pill.cancelado{background:linear-gradient(135deg,#f8d7da 0%,#e17055 100%);color:#842029;border-color:#d63031}
    /* Estilos mejorados para dashboard CRM */
     /* Alertas inteligentes */
     .alerts-section{margin:20px 0;padding:20px;background:linear-gradient(135deg,#fff5f5 0%,#fef2f2 100%);border:2px solid #fecaca;border-radius:16px;box-shadow:0 4px 15px rgba(239,68,68,.1)}
     .alerts-section h3{color:#dc2626;font-size:1.3rem;margin:0 0 15px 0;font-weight:600}
     .alerts-grid{display:grid;gap:10px}
     .alert-item{display:flex;justify-content:space-between;align-items:center;background:#fff;padding:12px 16px;border-radius:10px;border:1px solid #fecaca;box-shadow:0 2px 8px rgba(239,68,68,.08);transition:all 0.3s ease}
     .alert-item:hover{transform:translateX(5px);box-shadow:0 4px 15px rgba(239,68,68,.15)}
     .alert-dismiss{background:#dc2626;color:#fff;border:none;border-radius:50%;width:24px;height:24px;cursor:pointer;font-weight:bold;transition:all 0.3s ease}
     .alert-dismiss:hover{background:#b91c1c;transform:scale(1.1)}
     
     /* KPIs principales */
     .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin:20px 0}
     .kpi{background:linear-gradient(135deg,#f8f6f0 0%,#f0ede3 100%);border:1px solid #e8e3d3;border-radius:16px;padding:24px;text-align:center;box-shadow:0 8px 25px rgba(0,0,0,.08);transition:all 0.3s ease;position:relative;overflow:hidden}
     .kpi:hover{transform:translateY(-5px);box-shadow:0 12px 35px rgba(0,0,0,.12)}
     .kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--beige-osc),var(--beige-claro));border-radius:16px 16px 0 0}
     .kpi-icon{font-size:2rem;margin-bottom:10px}
     .kpi .val{font-size:2.5rem;font-weight:700;color:var(--beige-osc);margin-bottom:8px;text-shadow:0 2px 4px rgba(0,0,0,.1)}
     .kpi small{color:#8b7355;font-size:0.9rem;font-weight:500;text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:8px}
     .kpi-trend{font-size:0.8rem;color:#666;font-weight:500;background:rgba(255,255,255,0.7);padding:4px 8px;border-radius:12px;display:inline-block}
     
     /* Variaciones de color para KPIs */
     .kpi-primary::before{background:linear-gradient(90deg,#3b82f6,#60a5fa)}
     .kpi-success::before{background:linear-gradient(90deg,#10b981,#34d399)}
     .kpi-warning::before{background:linear-gradient(90deg,#f59e0b,#fbbf24)}
     .kpi-info::before{background:linear-gradient(90deg,#8b5cf6,#a78bfa)}
     
     /* KPIs secundarios */
     .kpis-secondary{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin:25px 0}
     .kpi-card{background:linear-gradient(135deg,#fff 0%,#fafafa 100%);border:2px solid #e8e3d3;border-radius:16px;padding:20px;box-shadow:0 6px 20px rgba(0,0,0,.08);transition:all 0.3s ease;position:relative;overflow:hidden}
     .kpi-card:hover{transform:translateY(-3px);box-shadow:0 10px 30px rgba(0,0,0,.12)}
     .kpi-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--beige-osc),var(--beige-claro))}
     .kpi-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
     .kpi-header h4{margin:0;color:var(--beige-osc);font-size:1.1rem;font-weight:600}
     .trend-indicator{font-size:1.2rem;font-weight:bold;padding:4px 8px;border-radius:8px}
     .trend-indicator.positive{color:#10b981;background:rgba(16,185,129,0.1)}
     .trend-indicator.negative{color:#ef4444;background:rgba(239,68,68,0.1)}
     .kpi-value{font-size:1.8rem;font-weight:700;color:var(--beige-osc);margin-bottom:5px;text-shadow:0 1px 3px rgba(0,0,0,.1)}
     .kpi-subtitle{font-size:0.85rem;color:#666;font-weight:500;text-transform:uppercase;letter-spacing:0.5px}
     
     /* Estilos para gráficos avanzados */
     .charts-section{margin:30px 0;padding:25px;background:linear-gradient(135deg,#faf9f5 0%,#f5f2ea 100%);border-radius:20px;border:1px solid #e8e3d3;box-shadow:0 8px 25px rgba(0,0,0,.08)}
     .charts-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;flex-wrap:wrap;gap:15px}
     .charts-header h3{color:var(--beige-osc);font-size:1.8rem;margin:0;font-weight:600;text-shadow:0 2px 4px rgba(0,0,0,.1)}
     .global-filters{display:flex;gap:12px;align-items:center}
     .filter-select{padding:10px 15px;border:2px solid var(--beige-osc);border-radius:10px;background:#fff;color:var(--beige-osc);font-weight:600;cursor:pointer;transition:all 0.3s ease}
     .filter-select:focus{outline:none;box-shadow:0 4px 15px rgba(139,115,85,.2);transform:translateY(-2px)}
     .btn-refresh{padding:10px 18px;background:linear-gradient(135deg,var(--beige-osc) 0%,#8b7355 100%);color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;transition:all 0.3s ease;box-shadow:0 3px 10px rgba(139,115,85,.3)}
     .btn-refresh:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(139,115,85,.4)}
     
     .charts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(400px,1fr));gap:25px;margin:20px 0}
     .chart-container{background:#fff;border:1px solid #e8e3d3;border-radius:16px;padding:25px;box-shadow:0 8px 25px rgba(0,0,0,.06);transition:all 0.3s ease;position:relative;overflow:hidden}
     .chart-container:hover{transform:translateY(-3px);box-shadow:0 12px 35px rgba(0,0,0,.1)}
     .chart-container::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,var(--beige-osc),var(--beige-claro))}
     .chart-large{grid-column:span 2}
     
     .chart-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px}
     .chart-header h4{margin:0;color:var(--beige-osc);font-size:1.2rem;font-weight:600}
     .chart-subtitle{font-size:0.9rem;color:#666;font-weight:500;font-style:italic}
     .chart-container canvas{max-height:320px}
     
     .chart-controls{display:flex;gap:8px;flex-wrap:wrap}
     .btn-chart{padding:8px 16px;border:2px solid var(--beige-osc);background:linear-gradient(135deg,#fff 0%,#faf9f5 100%);color:var(--beige-osc);border-radius:8px;cursor:pointer;font-size:0.85rem;font-weight:600;transition:all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);position:relative;overflow:hidden;box-shadow:0 2px 8px rgba(139,115,85,.1)}
     .btn-chart::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(139,115,85,0.1),transparent);transition:left 0.5s ease}
     .btn-chart:hover{background:linear-gradient(135deg,var(--beige-claro) 0%,#f0ede3 100%);transform:translateY(-2px) scale(1.02);box-shadow:0 4px 15px rgba(139,115,85,.2);border-color:#8b7355}
     .btn-chart:hover::before{left:100%}
     .btn-chart.active{background:linear-gradient(135deg,var(--beige-osc) 0%,#a0826d 50%,#8b7355 100%);color:#fff;box-shadow:0 4px 15px rgba(139,115,85,.3), inset 0 1px 0 rgba(255,255,255,.2);border-color:var(--beige-osc);transform:translateY(-1px)}
     .btn-chart:active{transform:translateY(0) scale(0.98);transition:all 0.1s ease}
     
     /* Insights y leyendas personalizadas */
     .chart-insights{display:flex;gap:20px;margin-top:15px;padding:15px;background:linear-gradient(135deg,#f8f6f0 0%,#f0ede3 100%);border-radius:12px;border:1px solid #e8e3d3}
     .insight-item{display:flex;flex-direction:column;gap:5px}
     .insight-label{font-size:0.8rem;color:#666;font-weight:500;text-transform:uppercase;letter-spacing:0.5px}
     .insight-value{font-size:1.1rem;font-weight:700;color:var(--beige-osc)}
     .chart-legend-custom{margin-top:15px;padding:15px;background:linear-gradient(135deg,#f8f6f0 0%,#f0ede3 100%);border-radius:12px;border:1px solid #e8e3d3}
     
     /* Responsive para gráficos */
     @media (max-width: 1200px) {
       .chart-large{grid-column:span 1}
       .charts-grid{grid-template-columns:repeat(auto-fit,minmax(350px,1fr))}
     }
     @media (max-width: 768px) {
       .charts-header{flex-direction:column;align-items:stretch}
       .global-filters{justify-content:center}
       .chart-header{flex-direction:column;align-items:stretch;gap:15px}
       .chart-controls{justify-content:center}
       .chart-insights{flex-direction:column;gap:10px}
     }
      
      /* Estilos para módulo de actividades */
      .activity-filters{margin:20px 0;padding:20px;background:linear-gradient(135deg,#f8f6f0 0%,#f0ede3 100%);border-radius:16px;border:1px solid #e8e3d3}
      .activity-filters h4{margin:0 0 15px 0;color:var(--beige-osc);font-size:1.2rem;font-weight:600}
      .filter-row{display:flex;gap:15px;align-items:center;flex-wrap:wrap}
      .filter-select{padding:10px 15px;border:2px solid var(--beige-osc);border-radius:10px;background:#fff;color:var(--beige-osc);font-weight:600;cursor:pointer;transition:all 0.3s ease;min-width:150px}
      .filter-select:focus{outline:none;box-shadow:0 4px 15px rgba(139,115,85,.2);transform:translateY(-2px)}
      
      .activity-type{display:inline-block;padding:6px 12px;border-radius:20px;font-size:0.85rem;font-weight:600;text-transform:capitalize}
      .activity-type.nota{background:linear-gradient(135deg,#e0f2fe 0%,#b3e5fc 100%);color:#01579b}
      .activity-type.llamada{background:linear-gradient(135deg,#f3e5f5 0%,#e1bee7 100%);color:#4a148c}
      .activity-type.email{background:linear-gradient(135deg,#fff3e0 0%,#ffcc02 100%);color:#e65100}
      .activity-type.reunion{background:linear-gradient(135deg,#e8f5e8 0%,#c8e6c9 100%);color:#1b5e20}
      .activity-type.tarea{background:linear-gradient(135deg,#fce4ec 0%,#f8bbd9 100%);color:#880e4f}
      .activity-type.seguimiento{background:linear-gradient(135deg,#f1f8e9 0%,#dcedc8 100%);color:#33691e}
      
      .priority-badge{display:inline-block;padding:4px 8px;border-radius:12px;font-size:0.8rem;font-weight:600;text-transform:capitalize}
      .priority-badge.alta{background:linear-gradient(135deg,#ffebee 0%,#ffcdd2 100%);color:#c62828;border:1px solid #ef5350}
      .priority-badge.media{background:linear-gradient(135deg,#fff8e1 0%,#ffecb3 100%);color:#f57c00;border:1px solid #ffb74d}
      .priority-badge.baja{background:linear-gradient(135deg,#e8f5e8 0%,#c8e6c9 100%);color:#2e7d32;border:1px solid #66bb6a}
      
      .activity-description{max-width:200px;word-wrap:break-word;font-size:0.9rem;line-height:1.4}
      
      /* Timeline de actividades */
      .activity-timeline{margin:30px 0;padding:25px;background:linear-gradient(135deg,#faf9f5 0%,#f5f2ea 100%);border-radius:16px;border:1px solid #e8e3d3}
      .activity-timeline h4{margin:0 0 20px 0;color:var(--beige-osc);font-size:1.3rem;font-weight:600}
      .timeline-container{position:relative;padding-left:30px}
      .timeline-container::before{content:'';position:absolute;left:15px;top:0;bottom:0;width:2px;background:linear-gradient(180deg,var(--beige-osc),var(--beige-claro))}
      
      .timeline-item{position:relative;margin-bottom:25px;background:#fff;border-radius:12px;padding:20px;box-shadow:0 4px 15px rgba(0,0,0,.08);border:1px solid #e8e3d3;transition:all 0.3s ease}
      .timeline-item:hover{transform:translateX(5px);box-shadow:0 8px 25px rgba(0,0,0,.12)}
      .timeline-item::before{content:'';position:absolute;left:-35px;top:20px;width:10px;height:10px;border-radius:50%;background:var(--beige-osc);border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.2)}
      
      .timeline-marker{position:absolute;left:-45px;top:15px;width:20px;height:20px;border-radius:50%;background:linear-gradient(135deg,var(--beige-osc) 0%,#8b7355 100%);display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:#fff;box-shadow:0 3px 10px rgba(139,115,85,.3)}
      
      .timeline-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
      .timeline-header strong{color:var(--beige-osc);font-size:1.1rem}
      .timeline-date{font-size:0.85rem;color:#666;font-weight:500}
      .timeline-description{margin-bottom:15px;line-height:1.5;color:#444}
      .timeline-footer{display:flex;justify-content:space-between;align-items:center;gap:10px}
      .timeline-employee{font-size:0.85rem;color:#666;font-weight:500}
      
      /* Responsive para actividades */
      @media (max-width: 768px) {
        .filter-row{flex-direction:column;align-items:stretch}
        .filter-select{min-width:auto}
        .timeline-container{padding-left:20px}
        .timeline-marker{left:-35px}
        .timeline-item::before{left:-25px}
        .timeline-header{flex-direction:column;align-items:flex-start;gap:5px}
        .timeline-footer{flex-direction:column;align-items:flex-start;gap:8px}
      }
      
      /* Encabezados de página modernos */
     .page-header{background:linear-gradient(135deg,#faf9f5 0%,#f5f2ea 100%);border:2px solid #e8e3d3;border-radius:16px;padding:25px;margin:25px 0;text-align:center;position:relative;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.08)}
     .page-header::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--beige-osc),var(--beige-claro))}
     .page-title{font-size:2.2rem;font-weight:700;color:var(--beige-osc);margin:0 0 10px 0;text-shadow:0 2px 4px rgba(0,0,0,.1);letter-spacing:-0.5px}
     .page-subtitle{font-size:1rem;color:#8b7355;font-weight:500;margin:0;text-transform:uppercase;letter-spacing:1px;opacity:0.9}
     
     /* Estilos para sección 'Sobre nosotros' moderna */
     .about-us-card{background:linear-gradient(135deg,#faf9f5 0%,#f5f2ea 100%);border:2px solid #e8e3d3;position:relative;overflow:hidden}
     .about-us-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--beige-osc),var(--beige-claro))}
     .about-header{display:flex;align-items:center;gap:15px;margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #e8e3d3}
     .about-icon{font-size:2.5rem;background:linear-gradient(135deg,var(--beige-osc) 0%,#8b7355 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
     .about-header h4{margin:0;color:var(--beige-osc);font-size:1.5rem;font-weight:700}
     .about-intro{font-size:1.05rem;line-height:1.6;color:#555;margin-bottom:25px;text-align:justify}
     .features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:25px}
     .feature-item{display:flex;align-items:flex-start;gap:15px;padding:15px;background:#fff;border-radius:12px;box-shadow:0 3px 10px rgba(0,0,0,.08);transition:all 0.3s ease;border:1px solid #f0f0f0}
     .feature-item:hover{transform:translateY(-3px);box-shadow:0 8px 25px rgba(0,0,0,.15);border-color:var(--beige-claro)}
     .feature-icon{font-size:1.8rem;flex-shrink:0;background:linear-gradient(135deg,#f8f6f0 0%,#f0ede3 100%);padding:8px;border-radius:50%;width:45px;height:45px;display:flex;align-items:center;justify-content:center}
     .feature-text{flex:1;font-size:0.95rem;line-height:1.5;color:#444}
     .feature-text strong{color:var(--beige-osc);font-weight:600}
     .about-cta{text-align:center;padding-top:20px;border-top:1px solid #e8e3d3}
     .btn-modern{display:inline-block;padding:12px 30px;background:linear-gradient(135deg,var(--beige-osc) 0%,#8b7355 100%);color:#fff;text-decoration:none;border-radius:25px;font-weight:600;font-size:1rem;box-shadow:0 4px 15px rgba(139,115,85,.3);transition:all 0.3s ease;border:2px solid transparent}
     .btn-modern:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(139,115,85,.4);border-color:rgba(255,255,255,0.2)}
     .btn-modern:active{transform:translateY(0);box-shadow:0 4px 15px rgba(139,115,85,.3)}
     
     /* Estilos para gestión de consultas */
     .consultas-section{margin:20px 0}
     .filters-bar{display:flex;gap:15px;margin-bottom:25px;padding:20px;background:linear-gradient(135deg,#faf9f5 0%,#f5f2ea 100%);border-radius:16px;border:2px solid #e8e3d3;box-shadow:0 4px 15px rgba(0,0,0,.05)}
     .filters-bar select{padding:12px 16px;border:2px solid #e8e3d3;border-radius:10px;background:#fff;font-size:0.95rem;color:var(--beige-osc);cursor:pointer;transition:all 0.3s ease;box-shadow:0 2px 8px rgba(0,0,0,.05)}
     .filters-bar select:focus{border-color:var(--beige-osc);box-shadow:0 4px 15px rgba(139,115,85,.2);outline:none}
     
     .consultas-grid{display:grid;gap:20px;grid-template-columns:repeat(auto-fit,minmax(400px,1fr))}
     .consulta-card{background:#fff;border:2px solid #e8e3d3;border-radius:16px;padding:20px;box-shadow:0 6px 20px rgba(0,0,0,.08);transition:all 0.3s ease;position:relative;overflow:hidden}
     .consulta-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--beige-osc),var(--beige-claro))}
     .consulta-card:hover{transform:translateY(-5px);box-shadow:0 12px 35px rgba(0,0,0,.15)}
     
     .consulta-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:15px;gap:15px}
     .consulta-info h4{margin:0 0 8px 0;color:var(--beige-osc);font-size:1.2rem;font-weight:600}
     .consulta-meta{display:flex;flex-direction:column;gap:4px}
     .consulta-meta span{font-size:0.85rem;color:#666;display:flex;align-items:center;gap:5px}
     .consulta-badges{display:flex;flex-direction:column;gap:8px;align-items:flex-end}
     
     .consulta-mensaje{margin-bottom:15px;padding:15px;background:linear-gradient(135deg,#f8f6f0 0%,#f5f2ea 100%);border-radius:12px;border:1px solid #e8e3d3}
     .consulta-mensaje strong{color:var(--beige-osc);font-weight:600}
     .consulta-mensaje p{margin:8px 0 0 0;line-height:1.5;color:#444}
     
     .consulta-respuesta{margin-bottom:15px;padding:15px;background:linear-gradient(135deg,#e8f5e8 0%,#d4edda 100%);border-radius:12px;border:1px solid #c3e6cb}
     .consulta-respuesta strong{color:#155724;font-weight:600}
     .consulta-respuesta p{margin:8px 0 0 0;line-height:1.5;color:#155724}
     .consulta-respuesta small{color:#6c757d;font-style:italic}
     
     .consulta-acciones{border-top:1px solid #e8e3d3;padding-top:15px}
     .respuesta-form .form-group{margin-bottom:15px}
     .respuesta-form label{display:block;margin-bottom:5px;color:var(--beige-osc);font-weight:600;font-size:0.9rem}
     .respuesta-form textarea{width:100%;min-height:80px;resize:vertical}
     .form-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
     .form-actions select{flex:1;min-width:120px}
     .form-actions .btn{flex-shrink:0}
     
     /* Estados de consultas */
     .estado-pendiente{background:linear-gradient(135deg,#fff3cd 0%,#ffeaa7 100%);color:#664d03;border-color:#f1c40f}
     .estado-respondida{background:linear-gradient(135deg,#d1e7dd 0%,#00b894 100%);color:#0f5132;border-color:#00a085}
     .estado-cerrada{background:linear-gradient(135deg,#e2e3e5 0%,#adb5bd 100%);color:#383d41;border-color:#6c757d}
     
     /* Prioridades de consultas */
     .prioridad-alta{background:linear-gradient(135deg,#f8d7da 0%,#e17055 100%);color:#842029;border-color:#d63031}
     .prioridad-media{background:linear-gradient(135deg,#fff3cd 0%,#ffeaa7 100%);color:#664d03;border-color:#f1c40f}
     .prioridad-baja{background:linear-gradient(135deg,#cfe2ff 0%,#74b9ff 100%);color:#084298;border-color:#0984e3}
     
     /* Estado vacío */
     .empty-state{text-align:center;padding:60px 20px;color:#666}
     .empty-icon{font-size:4rem;margin-bottom:20px;opacity:0.5}
     .empty-state h3{color:var(--beige-osc);margin-bottom:10px}
     .empty-state p{color:#888;font-size:1.1rem}
     
     /* Responsive para consultas */
     @media (max-width: 768px) {
       .consultas-grid{grid-template-columns:1fr}
       .filters-bar{flex-direction:column}
       .consulta-header{flex-direction:column;align-items:flex-start}
       .consulta-badges{flex-direction:row;align-items:flex-start}
       .form-actions{flex-direction:column;align-items:stretch}
       .form-actions select{min-width:auto}
     }
  </style>
</head>
<body>
  <!-- Sidebar moderno -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="logo">
        <i class="fas fa-tools"></i>
        <span>Admin Panel</span>
      </div>
      <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
      </button>
    </div>
    
    <nav class="sidebar-nav">
      <?php 
      $nav_icons = [
        'dashboard' => 'fas fa-chart-line',
        'pedidos' => 'fas fa-shopping-cart',
        'planes' => 'fas fa-clipboard-list',
        'clientes' => 'fas fa-users',
        'usuarios' => 'fas fa-user-cog',
        'empleados' => 'fas fa-hard-hat',
        'actividades' => 'fas fa-tasks',
        'galeria' => 'fas fa-images',
        'consultas' => 'fas fa-comments',
        'chatia' => 'fas fa-robot'
      ];
      
      foreach ($allowed as $e): 
        $isActive = $entity === $e;
        $icon = $nav_icons[$e] ?? 'fas fa-circle';
      ?>
        <a href="/admin/index.php?entity=<?php echo urlencode($e); ?>" 
           class="nav-item <?php echo $isActive ? 'active' : ''; ?>">
          <i class="<?php echo $icon; ?>"></i>
          <span class="nav-text"><?php echo ucfirst($e); ?></span>
          <?php if ($isActive): ?>
            <div class="active-indicator"></div>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    
    <div class="sidebar-footer">
      <a href="/index.php" class="footer-link">
        <i class="fas fa-home"></i>
        <span>Inicio</span>
      </a>
      <a href="/auth/logout.php" class="footer-link logout">
        <i class="fas fa-sign-out-alt"></i>
        <span>Cerrar sesión</span>
      </a>
    </div>
  </div>

  <!-- Contenido principal -->
  <div class="main-content">
    <div class="container section">
      <h2>Panel de administración</h2>

      <?php if ($flash_error): ?><div class="alert error"><?php echo htmlspecialchars($flash_error); ?></div><?php endif; ?>
      <?php if ($flash_success): ?><div class="alert success"><?php echo htmlspecialchars($flash_success); ?></div><?php endif; ?>

    <?php if ($entity!=='chatia'): ?>
      <div class="page-header">
        <h3 class="page-title">
          <?php 
          $icons = [
            'dashboard' => '📊',
            'planes' => '📋',
            'clientes' => '👥',
            'usuarios' => '👤',
            'empleados' => '👷',
            'pedidos' => '📦',
            'actividades' => '📞'
          ];
          echo ($icons[$entity] ?? '📄') . ' ' . ucfirst($entity);
          ?>
        </h3>
        <div class="page-subtitle">
          <?php 
          $subtitles = [
            'dashboard' => 'Panel de control y estadísticas',
            'planes' => 'Gestión de planes de servicio',
            'clientes' => 'Administración de clientes',
            'usuarios' => 'Control de usuarios del sistema',
            'empleados' => 'Gestión de personal',
            'pedidos' => 'Seguimiento de pedidos',
            'actividades' => 'Seguimiento de comunicaciones y tareas'
          ];
          echo $subtitles[$entity] ?? 'Gestión de datos';
          ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($entity==='dashboard'): ?>
      <!-- Alertas inteligentes -->
      <?php if (!empty($k_alertas)): ?>
        <div class="alerts-section">
          <h3>🚨 Alertas del Sistema</h3>
          <div class="alerts-grid">
            <?php foreach ($k_alertas as $alerta): ?>
              <div class="alert-item">
                <span><?php echo htmlspecialchars($alerta); ?></span>
                <button class="alert-dismiss" onclick="this.parentElement.style.display='none'">×</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- KPIs principales -->
      <div class="kpis">
        <div class="kpi kpi-primary">
          <div class="kpi-icon">👥</div>
          <div class="val"><?php echo number_format($k_total_clientes); ?></div>
          <small>Total clientes</small>
          <div class="kpi-trend">+<?php echo $k_nuevos_clientes_mes; ?> este mes</div>
        </div>
        <div class="kpi kpi-success">
          <div class="kpi-icon">📋</div>
          <div class="val"><?php echo number_format($k_total_pedidos); ?></div>
          <small>Total pedidos</small>
          <div class="kpi-trend"><?php echo $k_tasa_conversion; ?>% conversión</div>
        </div>
        <div class="kpi kpi-warning">
          <div class="kpi-icon">⏳</div>
          <div class="val"><?php echo number_format($k_pendientes); ?></div>
          <small>Pendientes</small>
          <div class="kpi-trend">Requieren atención</div>
        </div>
        <div class="kpi kpi-info">
          <div class="kpi-icon">💰</div>
          <div class="val">$<?php echo number_format($k_ingresos,2); ?></div>
          <small>Ingresos totales</small>
          <div class="kpi-trend">$<?php echo number_format($k_valor_promedio,2); ?> promedio</div>
        </div>
      </div>

      <!-- KPIs secundarios -->
      <div class="kpis-secondary">
        <div class="kpi-card">
          <div class="kpi-header">
            <h4>📈 Ingresos del Mes</h4>
            <span class="trend-indicator <?php echo $k_tendencia_ingresos >= 0 ? 'positive' : 'negative'; ?>">
              <?php echo $k_tendencia_ingresos >= 0 ? '↗' : '↘'; ?> <?php echo abs($k_tendencia_ingresos); ?>%
            </span>
          </div>
          <div class="kpi-value">$<?php echo number_format($k_ingresos_mes, 2); ?></div>
          <div class="kpi-subtitle">vs mes anterior</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-header">
            <h4>🏆 Empleado del Mes</h4>
          </div>
          <div class="kpi-value"><?php echo htmlspecialchars($k_empleado_top); ?></div>
          <div class="kpi-subtitle">Mayor rendimiento</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-header">
            <h4>⭐ Plan Popular</h4>
          </div>
          <div class="kpi-value"><?php echo htmlspecialchars($k_plan_popular); ?></div>
          <div class="kpi-subtitle">Más demandado</div>
        </div>
      </div>

      <!-- Sección de Gráficos -->
      <div class="charts-section">
        <div class="charts-header">
          <h3>📊 Análisis de Datos</h3>
          <div class="global-filters">
            <button class="btn-refresh" onclick="location.reload()">🔄 Actualizar</button>
          </div>
        </div>
        
        <div class="charts-grid">
          <div class="chart-container">
            <div class="chart-header">
              <h4>📈 Evolución de Pedidos e Ingresos</h4>
              <div class="chart-controls">
                <span class="chart-subtitle">Últimos 7 días</span>
              </div>
            </div>
            <canvas id="lineChart"></canvas>
          </div>
          
          <div class="chart-container">
            <div class="chart-header">
              <h4>🎯 Distribución por Plan</h4>
            </div>
            <canvas id="pieChart"></canvas>
          </div>
          
          <div class="chart-container">
            <div class="chart-header">
              <h4>💰 Ingresos por Plan</h4>
            </div>
            <canvas id="barChart"></canvas>
          </div>
        </div>
      </div>

      <div class="cards">
        <div class="card">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h4 style="margin: 0;">Actividad reciente</h4>
            <div style="display: flex; align-items: center; gap: 10px;">
              <label for="filasSelector" style="font-size: 14px; color: var(--beige-osc);">Mostrar:</label>
              <select id="filasSelector" onchange="cambiarFilas(this.value)" style="padding: 5px 10px; border: 1px solid var(--beige-osc); border-radius: 4px; background: white; font-size: 14px;">
                <option value="5" <?php echo ($filas_por_pagina == 5) ? 'selected' : ''; ?>>5 filas</option>
                <option value="10" <?php echo ($filas_por_pagina == 10) ? 'selected' : ''; ?>>10 filas</option>
                <option value="15" <?php echo ($filas_por_pagina == 15) ? 'selected' : ''; ?>>15 filas</option>
                <option value="20" <?php echo ($filas_por_pagina == 20) ? 'selected' : ''; ?>>20 filas</option>
                <option value="50" <?php echo ($filas_por_pagina == 50) ? 'selected' : ''; ?>>50 filas</option>
              </select>
            </div>
          </div>
          <?php if (empty($ultimos_pedidos)): ?>
            <p>No hay pedidos registrados aún.</p>
          <?php else: ?>
            <table class="table">
              <thead><tr><th>#</th><th>Cliente</th><th>Plan</th><th>Total</th><th>Estado</th><th>Fecha</th></tr></thead>
              <tbody>
                <?php foreach($ultimos_pedidos as $p): ?>
                  <tr>
                    <td><?php echo (int)$p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['cliente']); ?></td>
                    <td><?php echo htmlspecialchars($p['nombre_plan']); ?></td>
                    <td>$<?php echo number_format((float)$p['total'],2); ?></td>
                    <td><span class="pill <?php echo htmlspecialchars($p['estado']); ?>"><?php echo htmlspecialchars($p['estado']); ?></span></td>
                    <td><?php echo htmlspecialchars($p['fecha']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div class="card about-us-card">
          <div class="about-header">
            <div class="about-icon">🏠</div>
            <h4>Sobre nosotros</h4>
          </div>
          <div class="about-content">
            <p class="about-intro"><strong>Revive tu hogar</strong> es un estudio de interiorismo enfocado en transformar espacios reales con soluciones prácticas y alcanzables. Nuestro objetivo es que cada cliente pueda vivir mejor su casa, con decisiones de diseño claras y acompañamiento experto.</p>
            <div class="features-grid">
              <div class="feature-item">
                <div class="feature-icon">📋</div>
                <div class="feature-text">
                  <strong>Metodología simple:</strong> diagnóstico, propuesta visual y acompañamiento.
                </div>
              </div>
              <div class="feature-item">
                <div class="feature-icon">👥</div>
                <div class="feature-text">
                  <strong>Equipo multidisciplinario:</strong> diseñadores, arquitectos y especialistas en compras.
                </div>
              </div>
              <div class="feature-item">
                <div class="feature-icon">⭐</div>
                <div class="feature-text">
                  <strong>Calidad y cercanía:</strong> comunicación transparente y revisiones ágiles.
                </div>
              </div>
              <div class="feature-item">
                <div class="feature-icon">🤝</div>
                <div class="feature-text">
                  <strong>Compromiso:</strong> si no quedas conforme con la propuesta inicial, hacemos una iteración adicional sin costo.
                </div>
              </div>
            </div>
            <div class="about-cta">
              <a class="btn btn-modern" href="/planes.php">Ver planes</a>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($entity==='galeria'): ?>
      <?php
        // Crear tabla si no existe
        $tabla_creada = false;
        try {
          // Verificar si la tabla existe
          $result = $pdo->query("SHOW TABLES LIKE 'proyectos_galeria'");
          if ($result->rowCount() == 0) {
            // La tabla no existe, crearla
            $sql = "CREATE TABLE proyectos_galeria (
              id INT AUTO_INCREMENT PRIMARY KEY,
              titulo VARCHAR(255) NOT NULL,
              descripcion TEXT,
              imagen_url VARCHAR(255) NOT NULL,
              cliente_id INT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX (cliente_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $pdo->exec($sql);
            
            // Intentar agregar la foreign key por separado (puede fallar si no existe la tabla clientes)
            try {
              $pdo->exec("ALTER TABLE proyectos_galeria ADD CONSTRAINT fk_galeria_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL");
            } catch (Throwable $e) {
              // Ignorar error de foreign key si la tabla clientes no existe
            }
            
            $tabla_creada = true;
          }
        } catch (Throwable $e) {
          error_log('Error creando tabla proyectos_galeria: ' . $e->getMessage());
        }

        // El manejo de subida ahora está en el switch statement principal

        // Cargar clientes y elementos
        $clientes = $pdo->query('SELECT id, nombre FROM clientes ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = $pdo->query('SELECT g.*, c.nombre AS cliente_nombre FROM proyectos_galeria g LEFT JOIN clientes c ON c.id=g.cliente_id ORDER BY g.created_at DESC, g.id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
      ?>

      <h3>Galería de Proyectos</h3>
      <?php if ($tabla_creada): ?><div class="alert-modern alert-success">✓ Tabla proyectos_galeria creada correctamente</div><?php endif; ?>
      <?php if ($flash_success): ?><div class="alert-modern alert-success"><?php echo htmlspecialchars($flash_success); ?></div><?php endif; ?>
      <?php if ($flash_error): ?><div class="alert-modern alert-error"><?php echo htmlspecialchars($flash_error); ?></div><?php endif; ?>

      <!-- Modal de feedback para subida/errores de galería -->
      <style>
        .galeria-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .galeria-modal { background: #fff; border-radius: 12px; max-width: 480px; width: 90%; padding: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); border: 1px solid #eee; }
        .galeria-modal h4 { margin: 0 0 10px; font-size: 18px; }
        .galeria-modal .modal-actions { text-align: right; margin-top: 16px; }
        .galeria-modal .btn-close { background: #eee; border: none; padding: 8px 12px; border-radius: 8px; cursor: pointer; }
        .galeria-modal.error h4 { color: #dc2626; }
        .galeria-modal.success h4 { color: #16a34a; }
      </style>
      <div id="galeriaModalBackdrop" class="galeria-modal-backdrop" aria-hidden="true">
        <div id="galeriaModal" class="galeria-modal" role="dialog" aria-modal="true" aria-labelledby="galeriaModalTitle">
          <h4 id="galeriaModalTitle"></h4>
          <div id="galeriaModalMessage" style="color:#444"></div>
          <div class="modal-actions">
            <button type="button" class="btn-close" onclick="closeGaleriaModal()">Cerrar</button>
          </div>
        </div>
      </div>
      <script>
        function openGaleriaModal(title, message, type) {
          var back = document.getElementById('galeriaModalBackdrop');
          var box = document.getElementById('galeriaModal');
          var t = document.getElementById('galeriaModalTitle');
          var m = document.getElementById('galeriaModalMessage');
          t.textContent = title;
          m.textContent = message;
          box.classList.remove('error','success');
          if (type === 'error') { box.classList.add('error'); } else { box.classList.add('success'); }
          back.style.display = 'flex';
        }
        function closeGaleriaModal(){ document.getElementById('galeriaModalBackdrop').style.display='none'; }
        document.addEventListener('DOMContentLoaded', function() {
          var alertEl = document.querySelector('.alert-modern');
          if (alertEl) {
            var isError = alertEl.classList.contains('alert-error');
            var msg = alertEl.textContent.trim();
            openGaleriaModal(isError ? 'Error al subir proyecto' : 'Proyecto agregado', msg, isError ? 'error' : 'success');
          }
          
          // Debug del formulario
          var form = document.querySelector('form[enctype="multipart/form-data"]');
          if (form) {
            form.addEventListener('submit', function(e) {
              console.log('Formulario enviado');
              console.log('Action:', form.action);
              console.log('Method:', form.method);
              console.log('Enctype:', form.enctype);
              
              var formData = new FormData(form);
              console.log('Datos del formulario:');
              for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
              }
            });
          }
        });
      </script>

      <form class="row-form" method="post" enctype="multipart/form-data" style="margin-bottom:16px">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="upload">
        <input class="input" type="text" name="titulo" placeholder="Título" required>
        <textarea class="input" name="descripcion" placeholder="Descripción" rows="2"></textarea>
        <select class="input" name="cliente_id">
          <option value="">Sin cliente</option>
          <?php foreach($clientes as $c){ echo '<option value="'.(int)$c['id'].'">'.htmlspecialchars($c['nombre']).'</option>'; } ?>
        </select>
        <input class="input" type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp" required>
        <button class="btn">Subir Proyecto</button>
      </form>

      <!-- Información de debug -->
      <div style="background:#f8f9fa; padding:12px; border-radius:8px; margin-bottom:16px; font-size:12px; color:#666;">
        <strong>Info del sistema:</strong><br>
        • Límite de subida PHP: <?php echo ini_get('upload_max_filesize'); ?><br>
        • Límite POST: <?php echo ini_get('post_max_size'); ?><br>
        • Directorio uploads: <?php echo is_dir(__DIR__ . '/../uploads/proyectos') ? '✓ Existe' : '✗ No existe'; ?><br>
        • Permisos de escritura: <?php echo is_writable(__DIR__ . '/../uploads/proyectos') ? '✓ Sí' : '✗ No'; ?><br>
        • Extensión finfo: <?php echo function_exists('finfo_file') ? '✓ Disponible' : '✗ No disponible'; ?><br>
        • Tabla proyectos_galeria: <?php 
          try {
            $check = $pdo->query("SHOW TABLES LIKE 'proyectos_galeria'");
            echo $check->rowCount() > 0 ? '✓ Existe' : '✗ No existe';
          } catch (Throwable $e) {
            echo '✗ Error verificando';
          }
        ?><br>
        <a href="/admin/setup_galeria.php" style="color:#007bff; text-decoration:none;">🔧 Ejecutar setup completo</a>
      </div>

      <?php
        // Importar imágenes existentes desde la carpeta si no están en DB
        if ($_SERVER['REQUEST_METHOD']==='POST' && post_csrf_ok() && ($_POST['action']??'')==='import_galeria') {
          try {
            $dir = __DIR__ . '/../uploads/proyectos';
            if (is_dir($dir)) {
              $patterns = ['*.jpg','*.jpeg','*.png','*.webp'];
              $files = [];
              foreach ($patterns as $pat) {
                foreach (glob($dir.'/'.$pat, GLOB_BRACE) ?: [] as $p) { $files[] = $p; }
              }
              $inserted = 0;
              foreach ($files as $p) {
                $rel = '/uploads/proyectos/'.basename($p);
                $q = $pdo->prepare('SELECT COUNT(*) FROM proyectos_galeria WHERE imagen_url=?');
                $q->execute([$rel]);
                if ((int)$q->fetchColumn() === 0) {
                  $titulo = ucwords(str_replace(['-','_'],' ', pathinfo($p, PATHINFO_FILENAME)));
                  $st = $pdo->prepare('INSERT INTO proyectos_galeria (titulo, descripcion, imagen_url, cliente_id) VALUES (?, ?, ?, NULL)');
                  $st->execute([$titulo, '', $rel]);
                  $inserted++;
                }
              }
              $galeria_msg = $inserted>0 ? ("Importadas {$inserted} imágenes desde carpeta") : 'No hay nuevas imágenes para importar';
            } else {
              $galeria_msg = 'Carpeta de proyectos no encontrada';
            }
          } catch (Throwable $e) { $galeria_msg = 'Error al importar'; }
        }
      ?>

      <form method="post" style="margin-bottom:16px">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="import_galeria">
        <button class="btn btn-secondary">Importar desde carpeta uploads/proyectos</button>
      </form>

      <table class="table"><thead><tr><th>Imagen</th><th>Título</th><th>Descripción</th><th>Cliente</th><th>Fecha</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach($items as $it): ?>
          <tr>
            <td style="width:120px"><img src="<?php echo htmlspecialchars($it['imagen_url']); ?>" alt="" style="width:120px;height:auto;border-radius:8px"></td>
            <td><?php echo htmlspecialchars($it['titulo']); ?></td>
            <td><?php echo nl2br(htmlspecialchars($it['descripcion'])); ?></td>
            <td><?php echo htmlspecialchars($it['cliente_nombre'] ? '@'.$it['cliente_nombre'] : '—'); ?></td>
            <td><?php echo htmlspecialchars($it['created_at']); ?></td>
            <td>
              <form method="post" onsubmit="return confirm('¿Eliminar este elemento?');">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="delete_galeria">
                <input type="hidden" name="id" value="<?php echo (int)$it['id']; ?>">
                <button class="btn btn-danger">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>

    <?php if ($entity==='planes'): ?>
      <form class="row-form" method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="create">
        <input class="input" name="nombre_plan" placeholder="Nombre" required>
        <input class="input" name="precio" type="number" step="0.01" placeholder="Precio" required>
        <input class="input" name="duracion_dias" type="number" placeholder="Duración (días)">
        <textarea class="input" name="descripcion" placeholder="Descripción"></textarea>
        <button class="btn" type="submit">Crear</button>
      </form>
      <table class="table"><thead><tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Duración</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo htmlspecialchars($r['nombre_plan']); ?></td>
            <td><?php echo number_format((float)$r['precio'],2); ?></td>
            <td><?php echo (int)$r['duracion_dias']; ?></td>
            <td>
              <form method="post" class="row-form" style="margin-bottom:6px">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input class="input" name="nombre_plan" value="<?php echo htmlspecialchars($r['nombre_plan']); ?>">
                <input class="input" name="precio" type="number" step="0.01" value="<?php echo htmlspecialchars($r['precio']); ?>">
                <input class="input" name="duracion_dias" type="number" value="<?php echo htmlspecialchars($r['duracion_dias']); ?>">
                <input class="input" name="descripcion" value="<?php echo htmlspecialchars($r['descripcion']); ?>">
                <button class="btn" type="submit">Guardar</button>
              </form>
              <form method="post" onsubmit="return confirm('¿Eliminar plan?');">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <button class="btn btn-danger">Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>

    <?php if ($entity==='clientes'): ?>
      <form class="row-form" method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="create">
        <input class="input" name="nombre" placeholder="Nombre">
        <input class="input" name="apellido" placeholder="Apellido">
        <input class="input" name="correo" type="email" placeholder="Correo">
        <input class="input" name="telefono" placeholder="Teléfono">
        <input class="input" name="direccion" placeholder="Dirección">
        <select class="input" name="usuario_id"><option value="0">Sin usuario</option><?php foreach($usuarios as $u){echo '<option value="'.(int)$u['id'].'">'.htmlspecialchars($u['nombre_usuario']).'</option>'; } ?></select>
        <button class="btn">Crear</button>
      </form>
      <table class="table"><thead><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($rows as $r): $nom=trim(($r['nombre']??'').' '.($r['apellido']??'')); ?>
        <tr><td><?php echo (int)$r['id']; ?></td><td><?php echo htmlspecialchars($nom?:('Cliente #'.$r['id'])); ?></td><td><?php echo htmlspecialchars($r['correo']); ?></td>
          <td>
            <form method="post" class="row-form" style="margin-bottom:6px">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
              <input class="input" name="nombre" value="<?php echo htmlspecialchars($r['nombre']); ?>">
              <input class="input" name="apellido" value="<?php echo htmlspecialchars($r['apellido']); ?>">
              <input class="input" name="correo" value="<?php echo htmlspecialchars($r['correo']); ?>">
              <input class="input" name="telefono" value="<?php echo htmlspecialchars($r['telefono']); ?>">
              <input class="input" name="direccion" value="<?php echo htmlspecialchars($r['direccion']); ?>">
              <select class="input" name="usuario_id"><option value="0">Sin usuario</option><?php foreach($usuarios as $u){echo '<option '.sel($u['id'],$r['usuario_id']).' value="'.(int)$u['id'].'">'.htmlspecialchars($u['nombre_usuario']).'</option>'; } ?></select>
              <button class="btn">Guardar</button>
            </form>
            <form method="post" onsubmit="return confirm('¿Eliminar cliente?');"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-danger">Eliminar</button></form>
          </td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>

    <?php if ($entity==='usuarios'): ?>
      <form class="row-form" method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="create">
        <input class="input" name="nombre_usuario" placeholder="Usuario" required>
        <input class="input" name="correo_electronico" type="email" placeholder="Correo">
        <select class="input" name="rol"><option>cliente</option><option>proveedor</option><option>empleado</option><option>admin</option></select>
        <input class="input" name="contrasena" type="password" placeholder="Contraseña" required>
        <button class="btn">Crear</button>
      </form>
      <table class="table"><thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Correo</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td><?php echo (int)$r['id']; ?></td><td><?php echo htmlspecialchars($r['nombre_usuario']); ?></td><td><?php echo htmlspecialchars($r['rol']); ?></td><td><?php echo htmlspecialchars($r['correo_electronico']); ?></td>
            <td>
              <form method="post" class="row-form" style="margin-bottom:6px">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input class="input" name="nombre_usuario" value="<?php echo htmlspecialchars($r['nombre_usuario']); ?>">
                <input class="input" name="correo_electronico" value="<?php echo htmlspecialchars($r['correo_electronico']); ?>">
                <select class="input" name="rol"><option <?php echo sel('cliente',$r['rol']); ?>>cliente</option><option <?php echo sel('proveedor',$r['rol']); ?>>proveedor</option><option <?php echo sel('empleado',$r['rol']); ?>>empleado</option><option <?php echo sel('admin',$r['rol']); ?>>admin</option></select>
                <input class="input" name="contrasena" type="password" placeholder="Nueva contraseña (opcional)">
                <button class="btn">Guardar</button>
              </form>
              <form method="post" onsubmit="return confirm('¿Eliminar usuario?');"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-danger">Eliminar</button></form>
            </td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>

    <?php if ($entity==='empleados'): ?>
      <form class="row-form" method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="create">
        <input class="input" name="nombre" placeholder="Nombre">
        <input class="input" name="telefono" placeholder="Teléfono">
        <input class="input" name="correo" placeholder="Correo" type="email" required>
        <input class="input" name="contrasena" placeholder="Contraseña" type="password" required>
        <button class="btn">Crear</button>
      </form>
      <table class="table"><thead><tr><th>ID</th><th>Nombre</th><th>Teléfono</th><th>Correo</th><th>Pagos exitosos</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
          <tr><td><?php echo (int)$r['id']; ?></td><td><?php echo htmlspecialchars($r['nombre']); ?></td><td><?php echo htmlspecialchars($r['telefono']); ?></td><td><?php echo htmlspecialchars($r['correo']); ?></td><td><?php echo (int)($r['pagos_exitosos'] ?? 0); ?></td>
            <td>
              <form method="post" class="row-form" style="margin-bottom:6px">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <input class="input" name="nombre" value="<?php echo htmlspecialchars($r['nombre']); ?>">
                <input class="input" name="telefono" value="<?php echo htmlspecialchars($r['telefono']); ?>">
                <input class="input" name="correo" value="<?php echo htmlspecialchars($r['correo']); ?>">
                <button class="btn">Guardar</button>
              </form>
              <form method="post" onsubmit="return confirm('¿Eliminar empleado?');"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-danger">Eliminar</button></form>
            </td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>

    <?php if ($entity==='pedidos'): ?>
      <form class="row-form" method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="create">
        <select class="input" name="cliente_id" required><?php foreach($clientes as $c){echo '<option value="'.(int)$c['id'].'">'.htmlspecialchars($c['nombre']).'</option>'; } ?></select>
        <select class="input" name="plan_id" required><?php foreach($planes as $p){echo '<option value="'.(int)$p['id'].'">'.htmlspecialchars($p['nombre_plan']).'</option>'; } ?></select>
        <select class="input" name="empleado_id"><option value="">Sin empleado</option><?php foreach($empleados as $e){echo '<option value="'.(int)$e['id'].'">'.htmlspecialchars($e['nombre']).'</option>'; } ?></select>
        <input class="input" name="total" type="number" step="0.01" placeholder="Total" required>
        <select class="input" name="estado"><option>pendiente</option><option>enviado</option><option>entregado</option><option>cancelado</option></select>
        <button class="btn">Crear</button>
      </form>
      <table class="table"><thead><tr><th>ID</th><th>Cliente</th><th>Plan</th><th>Empleado</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo (int)$r['id']; ?></td>
            <td><?php echo htmlspecialchars($r['cliente_nombre']??('#'.$r['cliente_id'])); ?></td>
            <td><?php echo htmlspecialchars($r['nombre_plan']??('#'.$r['plan_id'])); ?></td>
            <td><?php echo htmlspecialchars($r['empleado_nombre']??'—'); ?></td>
            <td><?php echo number_format((float)$r['total'],2); ?></td>
            <td><?php echo htmlspecialchars($r['estado']); ?></td>
            <td>
              <form method="post" class="row-form" style="margin-bottom:6px">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                <select class="input" name="cliente_id"><?php foreach($clientes as $c){echo '<option '.sel($c['id'],$r['cliente_id']).' value="'.(int)$c['id'].'">'.htmlspecialchars($c['nombre']).'</option>'; } ?></select>
                <select class="input" name="plan_id"><?php foreach($planes as $p){echo '<option '.sel($p['id'],$r['plan_id']).' value="'.(int)$p['id'].'">'.htmlspecialchars($p['nombre_plan']).'</option>'; } ?></select>
                <select class="input" name="empleado_id"><option value="">Sin empleado</option><?php foreach($empleados as $e){echo '<option '.sel($e['id'],$r['empleado_id']).' value="'.(int)$e['id'].'">'.htmlspecialchars($e['nombre']).'</option>'; } ?></select>
                <input class="input" name="total" type="number" step="0.01" value="<?php echo htmlspecialchars($r['total']); ?>">
                <select class="input" name="estado"><option <?php echo sel('pendiente',$r['estado']); ?>>pendiente</option><option <?php echo sel('enviado',$r['estado']); ?>>enviado</option><option <?php echo sel('entregado',$r['estado']); ?>>entregado</option><option <?php echo sel('cancelado',$r['estado']); ?>>cancelado</option></select>
                <button class="btn">Guardar</button>
              </form>
              <form method="post" onsubmit="return confirm('¿Eliminar pedido?');"><input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-danger">Eliminar</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>

    <?php if ($entity==='actividades'): ?>
      <!-- Formulario para crear nueva actividad -->
      <form class="row-form" method="post">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
        <input type="hidden" name="action" value="create">
        <select class="input" name="tipo" required>
          <option value="nota">📝 Nota</option>
          <option value="llamada">📞 Llamada</option>
          <option value="email">📧 Email</option>
          <option value="reunion">🤝 Reunión</option>
          <option value="tarea">✅ Tarea</option>
          <option value="seguimiento">🔄 Seguimiento</option>
        </select>
        <select class="input" name="cliente_id" required>
          <option value="">Seleccionar cliente</option>
          <?php foreach($clientes as $c): ?>
            <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
          <?php endforeach; ?>
        </select>
        <select class="input" name="pedido_id">
          <option value="">Sin pedido específico</option>
          <?php 
          $pedidos_select = $pdo->query('SELECT p.id, c.nombre AS cliente_nombre FROM pedidos p LEFT JOIN clientes c ON c.id=p.cliente_id ORDER BY p.id DESC LIMIT 50')->fetchAll(PDO::FETCH_ASSOC);
          foreach($pedidos_select as $p): ?>
            <option value="<?php echo (int)$p['id']; ?>">Pedido #<?php echo (int)$p['id']; ?> - <?php echo htmlspecialchars($p['cliente_nombre']); ?></option>
          <?php endforeach; ?>
        </select>
        <select class="input" name="empleado_id" required>
          <option value="">Asignar empleado</option>
          <?php foreach($empleados as $e): ?>
            <option value="<?php echo (int)$e['id']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
          <?php endforeach; ?>
        </select>
        <textarea class="input" name="descripcion" placeholder="Descripción de la actividad" required rows="2"></textarea>
        <input class="input" name="fecha_programada" type="datetime-local" placeholder="Fecha programada (opcional)">
        <button class="btn">Registrar Actividad</button>
      </form>

      <!-- Filtros de actividades -->
      <div class="activity-filters">
        <h4>🔍 Filtros</h4>
        <div class="filter-row">
          <select id="filterTipo" class="filter-select">
            <option value="">Todos los tipos</option>
            <option value="nota">📝 Notas</option>
            <option value="llamada">📞 Llamadas</option>
            <option value="email">📧 Emails</option>
            <option value="reunion">🤝 Reuniones</option>
            <option value="tarea">✅ Tareas</option>
            <option value="seguimiento">🔄 Seguimientos</option>
          </select>
          <select id="filterEstado" class="filter-select">
            <option value="">Todos los estados</option>
            <option value="pendiente">⏳ Pendientes</option>
            <option value="completada">✅ Completadas</option>
            <option value="cancelada">❌ Canceladas</option>
          </select>
          <select id="filterPrioridad" class="filter-select">
            <option value="">Todas las prioridades</option>
            <option value="alta">🔴 Alta</option>
            <option value="media">🟡 Media</option>
            <option value="baja">🟢 Baja</option>
          </select>
          <button class="btn-refresh" onclick="filterActivities()">Filtrar</button>
        </div>
      </div>

      <!-- Tabla de actividades -->
      <table class="table" id="activitiesTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tipo</th>
            <th>Cliente</th>
            <th>Pedido</th>
            <th>Empleado</th>
            <th>Descripción</th>
            <th>Estado</th>
            <th>Prioridad</th>
            <th>Fecha</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr data-tipo="<?php echo htmlspecialchars($r['tipo']); ?>" data-estado="<?php echo htmlspecialchars($r['estado']); ?>" data-prioridad="<?php echo htmlspecialchars($r['prioridad']); ?>">
              <td><?php echo (int)$r['id']; ?></td>
              <td>
                <span class="activity-type <?php echo htmlspecialchars($r['tipo']); ?>">
                  <?php 
                  $icons = ['nota' => '📝', 'llamada' => '📞', 'email' => '📧', 'reunion' => '🤝', 'tarea' => '✅', 'seguimiento' => '🔄'];
                  echo ($icons[$r['tipo']] ?? '📄') . ' ' . ucfirst($r['tipo']); 
                  ?>
                </span>
              </td>
              <td><?php echo htmlspecialchars(($r['cliente_nombre'] ?? '') . ' ' . ($r['cliente_apellido'] ?? '')); ?></td>
              <td><?php echo $r['pedido_numero'] ? '#' . (int)$r['pedido_numero'] : '—'; ?></td>
              <td><?php echo htmlspecialchars($r['empleado_nombre'] ?? '—'); ?></td>
              <td class="activity-description"><?php echo htmlspecialchars(substr($r['descripcion'], 0, 100)) . (strlen($r['descripcion']) > 100 ? '...' : ''); ?></td>
              <td>
                <span class="pill <?php echo htmlspecialchars($r['estado']); ?>">
                  <?php echo htmlspecialchars($r['estado']); ?>
                </span>
              </td>
              <td>
                <span class="priority-badge <?php echo htmlspecialchars($r['prioridad']); ?>">
                  <?php 
                  $priority_icons = ['alta' => '🔴', 'media' => '🟡', 'baja' => '🟢'];
                  echo ($priority_icons[$r['prioridad']] ?? '⚪') . ' ' . ucfirst($r['prioridad']); 
                  ?>
                </span>
              </td>
              <td><?php echo date('d/m/Y H:i', strtotime($r['fecha_creacion'])); ?></td>
              <td>
                <form method="post" class="row-form" style="margin-bottom:6px">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <select class="input" name="estado">
                    <option <?php echo sel('pendiente',$r['estado']); ?> value="pendiente">⏳ Pendiente</option>
                    <option <?php echo sel('completada',$r['estado']); ?> value="completada">✅ Completada</option>
                    <option <?php echo sel('cancelada',$r['estado']); ?> value="cancelada">❌ Cancelada</option>
                  </select>
                  <select class="input" name="prioridad">
                    <option <?php echo sel('baja',$r['prioridad']); ?> value="baja">🟢 Baja</option>
                    <option <?php echo sel('media',$r['prioridad']); ?> value="media">🟡 Media</option>
                    <option <?php echo sel('alta',$r['prioridad']); ?> value="alta">🔴 Alta</option>
                  </select>
                  <textarea class="input" name="resultado" placeholder="Resultado/Notas" rows="2"><?php echo htmlspecialchars($r['resultado'] ?? ''); ?></textarea>
                  <button class="btn">Actualizar</button>
                </form>
                <form method="post" onsubmit="return confirm('¿Eliminar actividad?');">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                  <button class="btn btn-danger">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Timeline de actividades por cliente -->
      <div class="activity-timeline">
        <h4>📅 Timeline de Actividades Recientes</h4>
        <div class="timeline-container">
          <?php 
          $timeline_activities = $pdo->query('SELECT a.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido, e.nombre AS empleado_nombre FROM actividades a LEFT JOIN clientes c ON c.id=a.cliente_id LEFT JOIN empleados e ON e.id=a.empleado_id ORDER BY a.fecha_creacion DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
          foreach($timeline_activities as $activity): ?>
            <div class="timeline-item <?php echo htmlspecialchars($activity['tipo']); ?>">
              <div class="timeline-marker">
                <?php 
                $icons = ['nota' => '📝', 'llamada' => '📞', 'email' => '📧', 'reunion' => '🤝', 'tarea' => '✅', 'seguimiento' => '🔄'];
                echo $icons[$activity['tipo']] ?? '📄'; 
                ?>
              </div>
              <div class="timeline-content">
                <div class="timeline-header">
                  <strong><?php echo htmlspecialchars(($activity['cliente_nombre'] ?? '') . ' ' . ($activity['cliente_apellido'] ?? '')); ?></strong>
                  <span class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($activity['fecha_creacion'])); ?></span>
                </div>
                <div class="timeline-description"><?php echo htmlspecialchars($activity['descripcion']); ?></div>
                <div class="timeline-footer">
                  <span class="timeline-employee">👤 <?php echo htmlspecialchars($activity['empleado_nombre'] ?? 'Sin asignar'); ?></span>
                  <span class="pill <?php echo htmlspecialchars($activity['estado']); ?>"><?php echo htmlspecialchars($activity['estado']); ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($entity==='consultas'): ?>
      <div class="consultas-section">
        <h3>📧 Gestión de Consultas</h3>
        
        <!-- Filtros -->
        <div class="filters-bar">
          <select id="filtro-estado" onchange="filtrarConsultas()">
            <option value="">Todos los estados</option>
            <option value="pendiente">Pendientes</option>
            <option value="respondida">Respondidas</option>
            <option value="cerrada">Cerradas</option>
          </select>

        </div>

        <!-- Lista de consultas -->
        <div class="consultas-grid">
          <?php foreach ($rows as $consulta): ?>
            <div class="consulta-card" data-estado="<?php echo htmlspecialchars($consulta['estado'] ?? 'pendiente'); ?>">
              <div class="consulta-header">
                <div class="consulta-info">
                  <h4><?php echo htmlspecialchars($consulta['asunto']); ?></h4>
                  <div class="consulta-meta">
                    <span class="usuario">👤 <?php echo htmlspecialchars($consulta['nombre_usuario']); ?></span>
                    <span class="email">📧 <?php echo htmlspecialchars($consulta['correo_electronico']); ?></span>
                    <span class="fecha">📅 <?php echo date('d/m/Y H:i', strtotime($consulta['created_at'])); ?></span>
                  </div>
                </div>
                <div class="consulta-badges">
                  <span class="pill estado-<?php echo htmlspecialchars($consulta['estado'] ?? 'pendiente'); ?>">
                    <?php echo htmlspecialchars($consulta['estado'] ?? 'pendiente'); ?>
                  </span>
                </div>
              </div>
              
              <div class="consulta-mensaje">
                <strong>Mensaje:</strong>
                <p><?php echo nl2br(htmlspecialchars($consulta['mensaje'])); ?></p>
              </div>
              
              <?php if ($consulta['respuesta']): ?>
                <div class="consulta-respuesta">
                  <strong>Respuesta:</strong>
                  <p><?php echo nl2br(htmlspecialchars($consulta['respuesta'])); ?></p>
                  <small>Actualizada: <?php echo date('d/m/Y H:i', strtotime($consulta['updated_at'])); ?></small>
                </div>
              <?php endif; ?>
              
              <div class="consulta-acciones">
                <form method="post" class="respuesta-form">
                  <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="id" value="<?php echo (int)$consulta['id']; ?>">
                  
                  <div class="form-group">
                    <label>Respuesta:</label>
                    <textarea name="respuesta" class="input" rows="3" placeholder="Escribir respuesta..."><?php echo htmlspecialchars($consulta['respuesta'] ?? ''); ?></textarea>
                  </div>
                  
                  <div class="form-actions">
                    <select name="estado" class="input">
                      <option value="pendiente" <?php echo sel('pendiente', $consulta['estado']); ?>>Pendiente</option>
                      <option value="respondida" <?php echo sel('respondida', $consulta['estado']); ?>>Respondida</option>
                      <option value="cerrada" <?php echo sel('cerrada', $consulta['estado']); ?>>Cerrada</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <button type="submit" name="action" value="delete" class="btn btn-danger" onclick="return confirm('¿Eliminar consulta?');">Eliminar</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        
        <?php if (empty($rows)): ?>
          <div class="empty-state">
            <div class="empty-icon">📭</div>
            <h3>No hay consultas</h3>
            <p>Aún no se han recibido consultas de usuarios.</p>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($entity==='chatia'): ?>
      <!-- Chat IA (n8n) embebido en el panel admin -->
      <link href="https://cdn.jsdelivr.net/npm/@n8n/chat/dist/style.css" rel="stylesheet">
      <style>
        /* Overrides de theme del widget n8n para el look & feel del admin */
        :root {
          --chat--color-primary: #8C6A4F;
          --chat--color-primary-shade-50: #7b5e45;
          --chat--color-primary-shade-100: #6a523c;
          --chat--color-secondary: #D2B48C;
          --chat--color-secondary-shade-50: #c4a77f;
          --chat--color-dark: #2C2C2C;
          --chat--color-light: #FAF9F6;
          --chat--window--border-radius: 16px;
          --chat--message--border-radius: 16px;
          --chat--spacing: 1rem;
          --chat--message--font-size: 0.95rem;
          --chat--button--background: var(--chat--color-primary);
          --chat--button--color: var(--chat--color-light);
          --chat--input--send--button--color: var(--chat--color-secondary);
        }

        /* Se elimina el card y encabezado para dar más espacio al chat */

        /* Contenedor del chat (ocupa más espacio) */
        #n8n-chat-admin {
          height: 82vh !important;
          border-radius: 16px;
          overflow: hidden;
          background: linear-gradient(145deg, #ffffff 0%, #faf9f6 100%);
          border: none !important;
          box-shadow: none !important;
          display: flex;
          flex-direction: column;
          position: relative;
          width: 100%;
          margin: 0;
        }

        /* Asegurar que el widget ocupe todo el alto disponible */
        #n8n-chat-admin #n8n-chat {
          height: 100%;
        }

        /* Forzar layout en columna dentro del widget para anclar el input abajo */
        #n8n-chat-admin .n8n-chat-root,
        #n8n-chat-admin .n8n-chat-container,
        #n8n-chat-admin .n8n-chat-wrapper {
          display: flex !important;
          flex-direction: column !important;
          height: 100% !important;
        }

        /* La zona de mensajes crece y scrollea, con padding para el input */
        #n8n-chat-admin .n8n-chat-messages {
          flex: 1 1 auto !important;
          overflow-y: auto !important;
          padding-bottom: 90px !important;
        }

        /* Input pegado al fondo del contenedor */
        #n8n-chat-admin .n8n-chat-input-container {
          margin-top: auto !important;
          position: sticky !important;
          bottom: 0 !important;
          z-index: 2;
          backdrop-filter: saturate(140%) blur(6px);
        }

        @keyframes fadeInUp {
          from { opacity: 0; transform: translateY(8px); }
          to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 768px) {
          #n8n-chat-admin { height: 68vh; }
          .chatia-card { padding: 14px; border-radius: 14px; }
        }

        /* Mejoras de diseño adicionales y controles flotantes */
        #n8n-chat-admin {
          height: 84vh !important;
          border: 1px solid rgba(140, 106, 79, 0.10) !important;
          box-shadow: 0 20px 60px rgba(140, 106, 79, 0.18);
          background: linear-gradient(145deg, #ffffff 0%, #faf9f6 100%);
        }
        .chat-floating-actions { position: absolute; top: 12px; right: 12px; display: flex; gap: 10px; z-index: 3; }
        .chat-action-btn {
          appearance: none; border: none; cursor: pointer; width: 36px; height: 36px; border-radius: 50%;
          background: linear-gradient(135deg, #fff 0%, #f0ede7 100%);
          box-shadow: 0 6px 18px rgba(0,0,0,.12), inset 0 1px 0 rgba(255,255,255,.8);
          color: #8C6A4F; font-size: 18px; display: grid; place-items: center;
          transition: transform .2s ease, box-shadow .2s ease;
        }
        .chat-action-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 24px rgba(0,0,0,.18), inset 0 1px 0 rgba(255,255,255,.9); }
        .chat-action-btn:focus { outline: 2px solid rgba(140,106,79,.35); outline-offset: 2px; }

        /* Ocultar completamente el header interno del widget (refuerzo máximo) */
        #n8n-chat-admin .n8n-chat-header,
        #n8n-chat-slot .n8n-chat-header,
        .n8n-chat-header,
        #n8n-chat-admin header,
        #n8n-chat-slot header,
        #n8n-chat-admin [class*="header"],
        #n8n-chat-slot [class*="header"] {
          display: none !important;
          height: 0 !important;
          padding: 0 !important;
          margin: 0 !important;
          border: 0 !important;
          background: transparent !important;
        }
        #n8n-chat-admin .n8n-chat-title,
        #n8n-chat-admin .n8n-chat-subtitle,
        #n8n-chat-slot .n8n-chat-title,
        #n8n-chat-slot .n8n-chat-subtitle,
        .n8n-chat-title,
        .n8n-chat-subtitle { display: none !important; }

        /* Asegurar que cualquier contenedor superior del header no ocupe espacio */
        #n8n-chat-admin .n8n-chat-header-container,
        #n8n-chat-slot .n8n-chat-header-container,
        .n8n-chat-header-container { display: none !important; height: 0 !important; overflow: hidden !important; }

        /* Barra superior elegante con estado */
        /* Barra superior en flujo normal para que siempre se vea */
        .chat-topbar {
          position: relative;
          height: 64px;
          padding: 10px 16px;
          display: flex; align-items: center; justify-content: space-between;
          background: linear-gradient(135deg, rgba(255,255,255,0.85) 0%, rgba(245,242,234,0.85) 100%);
          border-bottom: 1px solid rgba(140,106,79,0.18);
          backdrop-filter: blur(8px) saturate(140%);
          z-index: 3;
        }
        .chat-agent { display: flex; align-items: center; gap: 12px; }
        .chat-avatar {
          width: 40px; height: 40px; border-radius: 50%; display: grid; place-items: center;
          background: linear-gradient(135deg, #8C6A4F 0%, #D2B48C 100%);
          color: #fff; box-shadow: 0 6px 18px rgba(140,106,79,0.25);
        }
        .chat-meta { display: flex; flex-direction: column; line-height: 1.1; }
        .chat-name { font-weight: 700; color: #5a4637; }
        .chat-status { font-size: 0.85rem; color: #7b5e45; display: flex; align-items: center; gap: 6px; }
        .chat-status .dot { width: 8px; height: 8px; border-radius: 50%; background: #19be55; box-shadow: 0 0 0 6px rgba(25,190,85,0.12); }

        /* Chips de sugerencias rápidas */
        /* Sugerencias bajo la barra superior en flujo normal */
        .chat-suggestions {
          position: relative;
          padding: 8px 16px; display: flex; gap: 10px; flex-wrap: wrap;
          z-index: 3;
        }
        .chat-suggestion {
          appearance: none; border: 1px solid rgba(140,106,79,0.2);
          background: linear-gradient(135deg, #fff 0%, #f7f4ed 100%);
          color: #5a4637; font-weight: 600; font-size: 0.9rem;
          padding: 8px 12px; border-radius: 999px; cursor: pointer;
          box-shadow: 0 4px 12px rgba(140,106,79,0.12);
          transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .chat-suggestion:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(140,106,79,0.18); border-color: rgba(140,106,79,0.35); }
        .chat-suggestion:active { transform: translateY(0); }

        /* Contenedor para alojar el widget n8n en su propia área */
        #n8n-chat-slot { flex: 1 1 auto; display: flex; }
        #n8n-chat-admin { display: flex; flex-direction: column; }
        /* El área de mensajes usa su padding original */
        #n8n-chat-admin .n8n-chat-messages { padding-top: 0 !important; }

        #n8n-chat-admin[data-theme="dark"] { background: linear-gradient(145deg, #1f1f1f 0%, #2a2a2a 100%); border-color: rgba(255,255,255,.08) !important; box-shadow: none; }
        #n8n-chat-admin[data-theme="dark"] .n8n-chat-header { background: linear-gradient(135deg, #2c2c2c 0%, #3a2f28 50%, #5a4637 100%) !important; }
        #n8n-chat-admin[data-theme="dark"] .n8n-chat-messages { background: linear-gradient(145deg, #242424 0%, #2b2b2b 100%) !important; }
        #n8n-chat-admin[data-theme="dark"] .n8n-chat-message.bot { background: linear-gradient(135deg, #2f2f2f 0%, #3a3a3a 100%) !important; color: #e9e9e9 !important; border-color: rgba(255,255,255,.08) !important; }
        #n8n-chat-admin[data-theme="dark"] .n8n-chat-input-container { background: linear-gradient(135deg, #2b2b2b 0%, #242424 100%) !important; border-top-color: rgba(255,255,255,.10) !important; }
        #n8n-chat-admin[data-theme="dark"] .n8n-chat-input { background: #1f1f1f !important; color: #eee !important; border-color: rgba(255,255,255,.12) !important; }
        #n8n-chat-admin[data-theme="dark"] .n8n-chat-input::placeholder { color: rgba(255,255,255,.6) !important; }
        #n8n-chat-admin[data-theme="dark"] .n8n-chat-send-button { background: radial-gradient(circle at 30% 30%, #6a523c, #8C6A4F) !important; }
      </style>
      <!-- Contenedor único del widget, sin encabezados ni card -->
      <div id="n8n-chat-admin">
        <div class="chat-topbar">
          <div class="chat-agent">
            <div class="chat-avatar">🤖</div>
            <div class="chat-meta">
              <span class="chat-name">Asistente IA</span>
              <span class="chat-status"><span class="dot"></span> Disponible</span>
            </div>
          </div>
        </div>
        <div class="chat-suggestions">
          <button class="chat-suggestion" data-suggestion="Ver estado de mi pedido">Estado de pedido</button>
          <button class="chat-suggestion" data-suggestion="Ayuda con cotización de servicio">Cotización</button>
          <button class="chat-suggestion" data-suggestion="Necesito soporte técnico">Soporte técnico</button>
        </div>
        <div id="n8n-chat-slot"></div>
        <div class="chat-floating-actions">
          <button id="chat-theme-toggle" class="chat-action-btn" title="Cambiar tema">🌙</button>
          <button id="chat-new-conv" class="chat-action-btn" title="Nueva conversación">↺</button>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($entity==='dashboard'): ?>
  <script>
    // Datos para gráficos con validación
    try {
      const datosLineales = <?php echo json_encode($datos_graficos['lineal_diario'] ?? []); ?>;
      const datosTorta = <?php echo json_encode($datos_graficos['torta'] ?? []); ?>;
      const datosBarras = <?php echo json_encode($datos_graficos['barras'] ?? []); ?>;
      
      // Función para cambiar cantidad de filas en tabla de actividad reciente
      function cambiarFilas(cantidad) {
        const url = new URL(window.location);
        url.searchParams.set('filas', cantidad);
        window.location.href = url.toString();
      }
      
      // Función para actualizar gráficos
      function refreshCharts() {
        location.reload();
      }
      
      // Función para cambiar vista de gráficos
      function switchChart(chartType, viewType) {
        console.log('Cambiando vista:', chartType, viewType);
        // Aquí se puede implementar la lógica para cambiar vistas
      }
    


    // Gráfico lineal - Evolución de pedidos e ingresos
    const lineChartElement = document.getElementById('lineChart');
    if (lineChartElement && datosLineales && datosLineales.length > 0) {
      const ctxLine = lineChartElement.getContext('2d');
      const lineChart = new Chart(ctxLine, {
      type: 'line',
      data: {
        labels: datosLineales.map(d => d.dia_nombre || d.periodo.split('-')[2] + '/' + d.periodo.split('-')[1]),
        datasets: [{
          label: 'Pedidos',
          data: datosLineales.map(d => d.total_pedidos),
          borderColor: 'rgb(75, 192, 192)',
          backgroundColor: 'rgba(75, 192, 192, 0.2)',
          borderWidth: 3,
          pointRadius: 6,
          pointHoverRadius: 8,
          pointBackgroundColor: 'rgb(75, 192, 192)',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          tension: 0.4,
          fill: false,
          yAxisID: 'y'
        }, {
          label: 'Ingresos ($)',
          data: datosLineales.map(d => d.ingresos_mes),
          borderColor: 'rgb(255, 99, 132)',
          backgroundColor: 'rgba(255, 99, 132, 0.2)',
          borderWidth: 3,
          pointRadius: 6,
          pointHoverRadius: 8,
          pointBackgroundColor: 'rgb(255, 99, 132)',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          tension: 0.4,
          fill: false,
          yAxisID: 'y1'
        }]
      },
      options: {
        responsive: true,
        interaction: {
          mode: 'index',
          intersect: false,
        },
        scales: {
          x: {
            display: true,
            title: {
              display: true,
              text: 'Día'
            }
          },
          y: {
            type: 'linear',
            display: true,
            position: 'left',
            title: {
              display: true,
              text: 'Cantidad de Pedidos'
            }
          },
          y1: {
            type: 'linear',
            display: true,
            position: 'right',
            title: {
              display: true,
              text: 'Ingresos ($)'
            },
            grid: {
              drawOnChartArea: false,
            },
          }
        }
      }
    });
    } // Cierre del if para lineChart

    // Gráfico de torta - Porcentaje de compra por plan
    const pieChartElement = document.getElementById('pieChart');
    if (pieChartElement && datosTorta && datosTorta.length > 0) {
      const ctxPie = pieChartElement.getContext('2d');
      new Chart(ctxPie, {
      type: 'pie',
      data: {
        labels: datosTorta.map(d => d.plan || 'Sin Plan'),
         datasets: [{
           data: datosTorta.map(d => d.cantidad),
           backgroundColor: [
             'rgba(255, 99, 132, 0.8)',   // Rojo
             'rgba(54, 162, 235, 0.8)',   // Azul
             'rgba(255, 205, 86, 0.8)',   // Amarillo
             'rgba(75, 192, 192, 0.8)',   // Verde
             'rgba(153, 102, 255, 0.8)',  // Morado
             'rgba(255, 159, 64, 0.8)',   // Naranja
             'rgba(199, 199, 199, 0.8)',  // Gris
             'rgba(83, 102, 255, 0.8)'    // Azul claro
           ],
           borderColor: [
             'rgba(255, 99, 132, 1)',
             'rgba(54, 162, 235, 1)',
             'rgba(255, 205, 86, 1)',
             'rgba(75, 192, 192, 1)',
             'rgba(153, 102, 255, 1)',
             'rgba(255, 159, 64, 1)',
             'rgba(199, 199, 199, 1)',
             'rgba(83, 102, 255, 1)'
           ],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'right',
            labels: {
              usePointStyle: true,
              padding: 20,
              font: {
                size: 14
              },
              generateLabels: function(chart) {
                const data = chart.data;
                if (data.labels.length && data.datasets.length) {
                  return data.labels.map((label, i) => {
                    const dataset = data.datasets[0];
                    const value = dataset.data[i];
                    const total = dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = ((value / total) * 100).toFixed(1);
                    return {
                      text: label + ': ' + value + ' (' + percentage + '%)',
                      fillStyle: dataset.backgroundColor[i],
                      strokeStyle: dataset.borderColor[i],
                      lineWidth: dataset.borderWidth,
                      hidden: false,
                      index: i
                    };
                  });
                }
                return [];
              }
            }
          },
          tooltip: {
            callbacks: {
              title: function(context) {
                return 'Plan: ' + context[0].label;
              },
              label: function(context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((context.parsed / total) * 100).toFixed(1);
                return 'Pedidos: ' + context.parsed + ' (' + percentage + '%)';
              }
            }
          }
        }
      }
    });
    } // Cierre del if para pieChart

    // Gráfico de barras - Ingresos por plan
    const barChartElement = document.getElementById('barChart');
    if (barChartElement && datosBarras && datosBarras.length > 0) {
      const ctxBar = barChartElement.getContext('2d');
      new Chart(ctxBar, {
       type: 'bar',
       data: {
         labels: datosBarras.map(d => d.nombre_plan || 'Sin plan'),
         datasets: [{
           label: 'Ingresos ($)',
           data: datosBarras.map(d => d.ingresos_total),
           backgroundColor: [
             'rgba(54, 162, 235, 0.8)',
             'rgba(255, 99, 132, 0.8)',
             'rgba(255, 205, 86, 0.8)',
             'rgba(75, 192, 192, 0.8)',
             'rgba(153, 102, 255, 0.8)',
             'rgba(255, 159, 64, 0.8)'
           ],
           borderColor: [
             'rgba(54, 162, 235, 1)',
             'rgba(255, 99, 132, 1)',
             'rgba(255, 205, 86, 1)',
             'rgba(75, 192, 192, 1)',
             'rgba(153, 102, 255, 1)',
             'rgba(255, 159, 64, 1)'
           ],
           borderWidth: 1
         }]
       },
       options: {
         responsive: true,
         scales: {
           y: {
             beginAtZero: true,
             title: {
               display: true,
               text: 'Ingresos ($)'
             },
             ticks: {
               callback: function(value) {
                 return '$' + value.toLocaleString();
               }
             }
           },
           x: {
             title: {
               display: true,
               text: 'Planes de Servicio'
             },
             ticks: {
               maxRotation: 45,
               minRotation: 0
             }
           }
         },
         plugins: {
           legend: {
             display: true,
             position: 'top'
           },
           tooltip: {
             callbacks: {
               title: function(context) {
                 return 'Plan: ' + context[0].label;
               },
               label: function(context) {
                 const planData = datosBarras[context.dataIndex];
                 return [
                   'Ingresos: $' + context.parsed.y.toLocaleString(),
                   'Total pedidos: ' + planData.total_pedidos
                 ];
               }
             }
           }
         }
       }
     });
     } // Cierre del if para barChart
     
    } catch (error) {
      console.error('Error al inicializar gráficos:', error);
    }
  </script>
  <?php endif; ?>

  <?php if ($entity==='chatia'): ?>
  <script type="module">
    import { createChat } from 'https://cdn.jsdelivr.net/npm/@n8n/chat/dist/chat.bundle.es.js';

    createChat({
      webhookUrl: 'http://localhost:5678/webhook/6f5ed248-bf29-4166-94ae-d852bc2051d0/chat',
      webhookConfig: {
        method: 'POST',
        headers: {}
      },
      target: '#n8n-chat-slot',
      mode: 'fullscreen',
      chatInputKey: 'chatInput',
      chatSessionKey: 'sessionId',
      loadPreviousSession: true,
      metadata: {},
      showWelcomeScreen: false,
      defaultLanguage: 'es',
      initialMessages: [
        '¡Hola! 👋',
        'Soy tu asistente de Revive tu Hogar. ¿En qué te ayudo hoy?'
      ],
      i18n: {
        es: {
          title: '',
          subtitle: '',
          footer: '',
          getStarted: 'Nueva conversación',
          inputPlaceholder: 'Escribe tu pregunta...'
        }
      },
      enableStreaming: false,
    });

    // Controles de UI: tema oscuro y nueva conversación
    const container = document.getElementById('n8n-chat-admin');
    const themeBtn = document.getElementById('chat-theme-toggle');
    const newBtn = document.getElementById('chat-new-conv');
    const suggestionBtns = document.querySelectorAll('.chat-suggestion');

    // Forzar eliminación del encabezado interno del widget (si reaparece)
    const removeChatHeader = () => {
      const headers = container.querySelectorAll('.n8n-chat-header, .n8n-chat-title, .n8n-chat-subtitle, .n8n-chat-header-container');
      headers.forEach(el => {
        el.style.display = 'none';
        el.style.height = '0';
        el.style.padding = '0';
        el.style.margin = '0';
        el.style.border = '0';
        el.style.background = 'transparent';
      });
    };
    // Ejecutar al cargar y observar el DOM por si el widget lo vuelve a crear
    removeChatHeader();
    const headerObserver = new MutationObserver(() => removeChatHeader());
    headerObserver.observe(container, { childList: true, subtree: true });
    if (themeBtn && container) {
      themeBtn.addEventListener('click', () => {
        const isDark = container.getAttribute('data-theme') === 'dark';
        container.setAttribute('data-theme', isDark ? 'light' : 'dark');
        themeBtn.textContent = isDark ? '🌙' : '☀️';
      });
    }
    if (newBtn) {
      newBtn.addEventListener('click', () => { window.location.reload(); });
    }

    // Sugerencias rápidas: rellenar input y enviar
    const waitForChatReady = () => new Promise(resolve => {
      const check = () => {
        const input = container.querySelector('.n8n-chat-input');
        const sendBtn = container.querySelector('.n8n-chat-send-button');
        if (input && sendBtn) { resolve({ input, sendBtn }); }
        else { setTimeout(check, 250); }
      };
      check();
    });

    if (suggestionBtns && suggestionBtns.length) {
      suggestionBtns.forEach(btn => {
        btn.addEventListener('click', async () => {
          const { input, sendBtn } = await waitForChatReady();
          input.value = btn.getAttribute('data-suggestion') || '';
          input.dispatchEvent(new Event('input', { bubbles: true }));
          sendBtn.click();
        });
      });
    }
  </script>
  <?php endif; ?>

  <?php if ($entity==='actividades'): ?>
  <script>
    // Las funciones de actividades están en admin.js
    console.log('Módulo de actividades cargado');
  </script>
  <?php endif; ?>

  <?php if ($entity==='consultas'): ?>
  <script>
    function filtrarConsultas() {
      const filtroEstado = document.getElementById('filtro-estado').value;
      const consultas = document.querySelectorAll('.consulta-card');
      
      consultas.forEach(consulta => {
        const estado = consulta.getAttribute('data-estado');
        
        const mostrarEstado = !filtroEstado || estado === filtroEstado;
        
        if (mostrarEstado) {
          consulta.style.display = 'block';
          consulta.style.animation = 'fadeIn 0.3s ease';
        } else {
          consulta.style.display = 'none';
        }
      });
      
      // Mostrar mensaje si no hay resultados
      const consultasVisibles = document.querySelectorAll('.consulta-card[style*="display: block"], .consulta-card:not([style*="display: none"])').length;
      let mensajeVacio = document.querySelector('.mensaje-filtro-vacio');
      
      if (consultasVisibles === 0 && filtroEstado) {
        if (!mensajeVacio) {
          mensajeVacio = document.createElement('div');
          mensajeVacio.className = 'mensaje-filtro-vacio empty-state';
          mensajeVacio.innerHTML = `
            <div class="empty-icon">🔍</div>
            <h3>No se encontraron consultas</h3>
            <p>No hay consultas que coincidan con los filtros seleccionados.</p>
          `;
          document.querySelector('.consultas-grid').appendChild(mensajeVacio);
        }
        mensajeVacio.style.display = 'block';
      } else if (mensajeVacio) {
        mensajeVacio.style.display = 'none';
      }
    }
    
    // Añadir animación CSS
    const style = document.createElement('style');
    style.textContent = `
      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }
    `;
    document.head.appendChild(style);
    
    console.log('Módulo de consultas cargado');
  </script>
  <?php endif; ?>

  <script>
    // Funcionalidad del sidebar móvil
    document.addEventListener('DOMContentLoaded', function() {
      const sidebarToggle = document.querySelector('.sidebar-toggle');
      const sidebar = document.querySelector('.sidebar');
      
      if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
          sidebar.classList.toggle('active');
        });

        // Cerrar sidebar al hacer clic fuera de él en móviles
        document.addEventListener('click', function(e) {
          if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
              sidebar.classList.remove('active');
            }
          }
        });

        // Manejar redimensionamiento de ventana
        window.addEventListener('resize', function() {
          if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
          }
        });
      }
    });
  </script>


</body>
</style>
</html>