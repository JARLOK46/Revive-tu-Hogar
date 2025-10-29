<?php
// setup_db.php - Recrea la base de datos con tablas y datos semilla
// Uso: php setup_db.php

declare(strict_types=1);

// Configuración (alineada con app/config/db.php)
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'revivetuhogar';
$DB_CHARSET = 'utf8mb4';

function p($msg){ fwrite(STDOUT, $msg . PHP_EOL); }
function pe($msg){ fwrite(STDERR, $msg . PHP_EOL); }

try {
  // 1) Conexión sin base de datos para crearla
  $pdoRoot = new PDO("mysql:host=$DB_HOST;charset=$DB_CHARSET", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET $DB_CHARSET COLLATE {$DB_CHARSET}_general_ci");
  p("✓ Base de datos '$DB_NAME' preparada");

  // 2) Conexión a la base de datos
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  // 3) Crear tablas (orden por dependencias)
  $ddl = [
    // usuarios
    'CREATE TABLE IF NOT EXISTS usuarios (
       id INT AUTO_INCREMENT PRIMARY KEY,
       nombre_usuario VARCHAR(100) NOT NULL UNIQUE,
       contrasena_hash VARCHAR(255) NOT NULL,
       rol ENUM("admin","empleado","cliente") NOT NULL,
       correo_electronico VARCHAR(255) NOT NULL UNIQUE,
       fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // clientes
    'CREATE TABLE IF NOT EXISTS clientes (
       id INT AUTO_INCREMENT PRIMARY KEY,
       usuario_id INT NULL,
       nombre VARCHAR(100) DEFAULT NULL,
       apellido VARCHAR(100) DEFAULT NULL,
       telefono VARCHAR(50) DEFAULT NULL,
       correo VARCHAR(255) DEFAULT NULL,
       direccion TEXT DEFAULT NULL,
       fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       INDEX (usuario_id),
       CONSTRAINT fk_clientes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // empleados
    'CREATE TABLE IF NOT EXISTS empleados (
       id INT AUTO_INCREMENT PRIMARY KEY,
       usuario_id INT NOT NULL,
       nombre VARCHAR(100) NOT NULL,
       cargo VARCHAR(100) DEFAULT "empleado",
       telefono VARCHAR(50) DEFAULT NULL,
       correo VARCHAR(255) DEFAULT NULL,
       fecha_contratacion DATE DEFAULT (CURDATE()),
       INDEX (usuario_id),
       CONSTRAINT fk_empleados_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // planes
    'CREATE TABLE IF NOT EXISTS planes (
       id INT AUTO_INCREMENT PRIMARY KEY,
       nombre_plan VARCHAR(100) NOT NULL,
       descripcion TEXT DEFAULT NULL,
       precio DECIMAL(10,2) NOT NULL,
       duracion_dias INT NOT NULL
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // pedidos
    'CREATE TABLE IF NOT EXISTS pedidos (
       id INT AUTO_INCREMENT PRIMARY KEY,
       cliente_id INT NOT NULL,
       plan_id INT NOT NULL,
       empleado_id INT NULL,
       fecha DATETIME DEFAULT CURRENT_TIMESTAMP,
       total DECIMAL(10,2) NOT NULL,
       estado ENUM("pendiente","enviado","entregado","cancelado","completado") DEFAULT "pendiente",
       INDEX (cliente_id),
       INDEX (plan_id),
       INDEX (empleado_id),
       CONSTRAINT fk_pedidos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
       CONSTRAINT fk_pedidos_plan FOREIGN KEY (plan_id) REFERENCES planes(id) ON DELETE CASCADE,
       CONSTRAINT fk_pedidos_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // facturas
    'CREATE TABLE IF NOT EXISTS facturas (
       id INT AUTO_INCREMENT PRIMARY KEY,
       pedido_id INT NOT NULL,
       plan_id INT NOT NULL,
       monto DECIMAL(10,2) NOT NULL,
       monto_total DECIMAL(10,2) GENERATED ALWAYS AS (monto) STORED,
       estado_pago ENUM("pendiente","pagado","cancelado") DEFAULT "pendiente",
       fecha_factura DATETIME DEFAULT CURRENT_TIMESTAMP,
       fecha_pago DATETIME DEFAULT NULL,
       INDEX (pedido_id),
       INDEX (plan_id),
       CONSTRAINT fk_facturas_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
       CONSTRAINT fk_facturas_plan FOREIGN KEY (plan_id) REFERENCES planes(id) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // consultas
    'CREATE TABLE IF NOT EXISTS consultas (
       id INT AUTO_INCREMENT PRIMARY KEY,
       usuario_id INT NOT NULL,
       asunto VARCHAR(255) NOT NULL,
       mensaje TEXT NOT NULL,
       respuesta TEXT DEFAULT NULL,
       estado ENUM("pendiente","respondida") DEFAULT "pendiente",
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       INDEX (usuario_id),
       CONSTRAINT fk_consultas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // actividades
    'CREATE TABLE IF NOT EXISTS actividades (
       id INT AUTO_INCREMENT PRIMARY KEY,
       tipo ENUM("nota","llamada","email","reunion","tarea","seguimiento") DEFAULT "nota",
       descripcion TEXT NOT NULL,
       cliente_id INT NULL,
       pedido_id INT NULL,
       empleado_id INT NULL,
       fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       fecha_programada DATETIME DEFAULT NULL,
       estado ENUM("pendiente","completada","cancelada") DEFAULT "pendiente",
       prioridad ENUM("baja","media","alta") DEFAULT "media",
       resultado TEXT DEFAULT NULL,
       INDEX (cliente_id),
       INDEX (pedido_id),
       INDEX (empleado_id),
       INDEX (fecha_creacion)
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // proyectos_galeria
    'CREATE TABLE IF NOT EXISTS proyectos_galeria (
       id INT AUTO_INCREMENT PRIMARY KEY,
       titulo VARCHAR(255) NOT NULL,
       descripcion TEXT DEFAULT NULL,
       imagen_url VARCHAR(255) NOT NULL,
       cliente_id INT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       INDEX (cliente_id),
       CONSTRAINT fk_galeria_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // cliente_preferencias
    'CREATE TABLE IF NOT EXISTS cliente_preferencias (
       cliente_id INT NOT NULL PRIMARY KEY,
       email_notif TINYINT(1) DEFAULT 1,
       whatsapp_notif TINYINT(1) DEFAULT 0,
       newsletter TINYINT(1) DEFAULT 0,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       CONSTRAINT fk_pref_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // productos (compatibilidad con detallespedidos)
    'CREATE TABLE IF NOT EXISTS productos (
       id INT AUTO_INCREMENT PRIMARY KEY,
       nombre VARCHAR(100) NOT NULL,
       descripcion TEXT DEFAULT NULL,
       precio DECIMAL(10,2) NOT NULL,
       stock INT(11) NOT NULL DEFAULT 0,
       categoria VARCHAR(50) DEFAULT NULL,
       proveedor_id INT(11) DEFAULT NULL,
       INDEX (proveedor_id)
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // proveedores
    'CREATE TABLE IF NOT EXISTS proveedores (
       id INT AUTO_INCREMENT PRIMARY KEY,
       nombre VARCHAR(100) NOT NULL,
       contacto VARCHAR(100) DEFAULT NULL,
       telefono VARCHAR(15) DEFAULT NULL,
       direccion TEXT DEFAULT NULL
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,

    // FK productos -> proveedores
    'ALTER TABLE productos
       ADD CONSTRAINT fk_productos_proveedor FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL',

    // detallespedidos
    'CREATE TABLE IF NOT EXISTS detallespedidos (
       id INT AUTO_INCREMENT PRIMARY KEY,
       pedido_id INT NOT NULL,
       producto_id INT NULL,
       cantidad INT NOT NULL,
       precio_unitario DECIMAL(10,2) NOT NULL,
       INDEX (pedido_id),
       INDEX (producto_id),
       CONSTRAINT fk_detalle_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
       CONSTRAINT fk_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL
     ) ENGINE=InnoDB DEFAULT CHARSET='.$DB_CHARSET,
  ];

  foreach ($ddl as $sql) { $pdo->exec($sql); }
  p('✓ Tablas creadas/actualizadas');

  // 4) Semilla básica
  $adminUser = 'admin';
  $empUser   = 'empleado1';
  $cliUser   = 'cliente1';
  $pwdAdmin  = password_hash('Admin123!', PASSWORD_BCRYPT);
  $pwdEmp    = password_hash('Empleado123!', PASSWORD_BCRYPT);
  $pwdCli    = password_hash('Cliente123!', PASSWORD_BCRYPT);

  $insUsuario = $pdo->prepare('INSERT IGNORE INTO usuarios (nombre_usuario, contrasena_hash, rol, correo_electronico, fecha_registro) VALUES (?,?,?,?, NOW())');
  $insUsuario->execute([$adminUser, $pwdAdmin, 'admin', 'admin@revivetuhogar.local']);
  $insUsuario->execute([$empUser,   $pwdEmp,   'empleado', 'empleado1@revivetuhogar.local']);
  $insUsuario->execute([$cliUser,   $pwdCli,   'cliente',  'cliente1@revivetuhogar.local']);

  $uidAdmin = (int)$pdo->query('SELECT id FROM usuarios WHERE nombre_usuario="'.$adminUser.'"')->fetchColumn();
  $uidEmp   = (int)$pdo->query('SELECT id FROM usuarios WHERE nombre_usuario="'.$empUser.'"')->fetchColumn();
  $uidCli   = (int)$pdo->query('SELECT id FROM usuarios WHERE nombre_usuario="'.$cliUser.'"')->fetchColumn();

  // Empleado
  $pdo->prepare('INSERT IGNORE INTO empleados (usuario_id, nombre, cargo, telefono, correo, fecha_contratacion) VALUES (?,?,?,?,?, CURDATE())')
      ->execute([$uidEmp, 'Juan Pérez', 'Consultor', '555-000-111', 'empleado1@revivetuhogar.local']);

  $empId = (int)$pdo->query('SELECT id FROM empleados WHERE usuario_id='.$uidEmp)->fetchColumn();

  // Cliente
  $pdo->prepare('INSERT IGNORE INTO clientes (usuario_id, nombre, apellido, telefono, correo, direccion, fecha_registro) VALUES (?,?,?,?,?,?, NOW())')
      ->execute([$uidCli, 'Ana', 'García', '555-123-456', 'cliente1@revivetuhogar.local', 'Av. Principal 123, Ciudad']);

  $cliId = (int)$pdo->query('SELECT id FROM clientes WHERE usuario_id='.$uidCli)->fetchColumn();

  // Planes
  $pdo->prepare('INSERT IGNORE INTO planes (id, nombre_plan, descripcion, precio, duracion_dias) VALUES (?,?,?,?,?)')
      ->execute([1, 'Esencial', 'Plan básico para asesorías puntuales.', 99.00, 30]);
  $pdo->prepare('INSERT IGNORE INTO planes (id, nombre_plan, descripcion, precio, duracion_dias) VALUES (?,?,?,?,?)')
      ->execute([2, 'Confort', 'Plan intermedio con seguimiento mensual.', 199.00, 60]);
  $pdo->prepare('INSERT IGNORE INTO planes (id, nombre_plan, descripcion, precio, duracion_dias) VALUES (?,?,?,?,?)')
      ->execute([3, 'Premium', 'Plan avanzado con acompañamiento completo.', 299.00, 90]);

  // Pedido + Factura
  $pdo->prepare('INSERT IGNORE INTO pedidos (id, cliente_id, plan_id, empleado_id, fecha, total, estado) VALUES (?,?,?,?, NOW(), ?, "pendiente")')
      ->execute([1, $cliId, 1, $empId ?: null, 99.00]);
  $pdo->prepare('INSERT IGNORE INTO facturas (id, pedido_id, plan_id, monto, estado_pago, fecha_factura) VALUES (?,?,?,?, "pendiente", NOW())')
      ->execute([1, 1, 1, 99.00]);

  p('✓ Datos semilla insertados');

  // Conteos finales
  $counts = [
    'usuarios'   => (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(),
    'clientes'   => (int)$pdo->query('SELECT COUNT(*) FROM clientes')->fetchColumn(),
    'empleados'  => (int)$pdo->query('SELECT COUNT(*) FROM empleados')->fetchColumn(),
    'planes'     => (int)$pdo->query('SELECT COUNT(*) FROM planes')->fetchColumn(),
    'pedidos'    => (int)$pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn(),
    'facturas'   => (int)$pdo->query('SELECT COUNT(*) FROM facturas')->fetchColumn(),
    'consultas'  => (int)$pdo->query('SELECT COUNT(*) FROM consultas')->fetchColumn(),
    'actividades'=> (int)$pdo->query('SELECT COUNT(*) FROM actividades')->fetchColumn(),
    'galeria'    => (int)$pdo->query('SELECT COUNT(*) FROM proyectos_galeria')->fetchColumn(),
  ];
  foreach ($counts as $t=>$c) { p("→ $t: $c registros"); }

  p('✓ Base de datos lista');
} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
  pe('Error: '.$e->getMessage());
  exit(1);
}

?>