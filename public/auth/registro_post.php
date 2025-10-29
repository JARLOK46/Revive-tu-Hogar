<?php
require_once __DIR__.'/../../app/config/session.php';
require_once __DIR__.'/../../app/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    header('Location: /auth/registro.php');
    exit;
}
if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) { 
    $_SESSION['flash_error'] = 'La sesión expiró. Inténtalo de nuevo.';
    header('Location: /auth/registro.php');
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$telefono = trim($_POST['telefono'] ?? '');

if ($nombre === '' || $apellido === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    $_SESSION['flash_error'] = 'Datos inválidos. Verifica nombre, apellido, correo válido y contraseña (mín. 6).';
    header('Location: /auth/registro.php');
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) { 
    $_SESSION['flash_error'] = 'Error de conexión a la base de datos.';
    header('Location: /auth/registro.php');
    exit;
}

try {
    // Bloquear únicamente si el correo ya existe en usuarios (credenciales)
    $chkUsuario = $pdo->prepare('SELECT id FROM usuarios WHERE correo_electronico = ? LIMIT 1');
    $chkUsuario->execute([$email]);
    $existsUsuario = (bool)$chkUsuario->fetchColumn();

    if ($existsUsuario) {
        error_log('[registro] conflicto: correo ya existe en usuarios => ' . $email);
        $_SESSION['flash_error'] = 'Este correo ya tiene una cuenta. Por favor, inicia sesión.';
        header('Location: /auth/registro.php');
        exit;
    }

    $pdo->beginTransaction();

    // Derivar nombre_usuario a partir del email (antes de @)
    $baseUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', strstr($email, '@', true) ?: 'usuario');
    if ($baseUser === '') { $baseUser = 'usuario'; }
    $nombre_usuario = $baseUser;

    // Asegurar unicidad de nombre_usuario
    $suf = 0;
    $checkUserStmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE nombre_usuario = ? LIMIT 1');
    while (true) {
        $checkUserStmt->execute([$nombre_usuario]);
        if (!$checkUserStmt->fetchColumn()) { break; }
        $suf++;
        $nombre_usuario = $baseUser . $suf;
    }

    // Crear usuario con rol cliente
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO usuarios (nombre_usuario, contrasena_hash, rol, correo_electronico, fecha_registro) VALUES (?,?,?,?, NOW())');
    $stmt->execute([$nombre_usuario, $hash, 'cliente', $email]);
    $usuario_id = (int)$pdo->lastInsertId();

    // Buscar cliente por correo para reutilizarlo si no tiene usuario_id
    $selCliente = $pdo->prepare('SELECT id, usuario_id FROM clientes WHERE correo = ? LIMIT 1');
    $selCliente->execute([$email]);
    $cliente = $selCliente->fetch(PDO::FETCH_ASSOC);

    if ($cliente && empty($cliente['usuario_id'])) {
        // Vincular cliente existente a este usuario y actualizar datos básicos
        $upd = $pdo->prepare('UPDATE clientes SET usuario_id = ?, nombre = ?, apellido = ?, telefono = ?, fecha_registro = NOW() WHERE id = ?');
        $upd->execute([$usuario_id, $nombre, $apellido, $telefono, (int)$cliente['id']]);
        error_log('[registro] cliente existente vinculado id='.(int)$cliente['id'].' usuario_id='.$usuario_id);
    } else if (!$cliente) {
        // Crear cliente nuevo
        $stmt2 = $pdo->prepare('INSERT INTO clientes (usuario_id, nombre, apellido, telefono, correo, direccion, fecha_registro) VALUES (?,?,?,?,?,?, NOW())');
        $stmt2->execute([$usuario_id, $nombre, $apellido, $telefono, $email, null]);
        error_log('[registro] cliente creado para usuario_id='.$usuario_id);
    } else {
        // Ya existe un cliente con este correo y usuario asociado
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('[registro] conflicto: correo ya asociado a usuario en clientes => ' . $email);
        $_SESSION['flash_error'] = 'Este correo ya está asociado a una cuenta. Intenta iniciar sesión.';
        header('Location: /auth/registro.php');
        exit;
    }

    $pdo->commit();

    $_SESSION['user_id'] = $usuario_id;
    $_SESSION['rol'] = 'cliente';

    header('Location: /cliente/dashboard.php');
    exit;
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('[registro] error: '. $e->getMessage());
    $_SESSION['flash_error'] = 'Error en el registro. Inténtalo nuevamente.';
    header('Location: /auth/registro.php');
    exit;
}