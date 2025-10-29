<?php
require_once __DIR__.'/../../app/config/session.php';
require_once __DIR__.'/../../app/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /auth/login.php');
    exit;
}

if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
    $_SESSION['flash_error'] = 'La sesión expiró. Inténtalo de nuevo.';
    header('Location: /auth/login.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    $_SESSION['flash_error'] = 'Por favor, completa un correo válido y contraseña.';
    header('Location: /auth/login.php');
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $_SESSION['flash_error'] = 'Error de conexión a la base de datos.';
    header('Location: /auth/login.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, correo_electronico, contrasena_hash, rol FROM usuarios WHERE correo_electronico = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['contrasena_hash'])) {
        $_SESSION['flash_error'] = 'Correo o contraseña incorrectos.';
        header('Location: /auth/login.php');
        exit;
    }

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['rol'] = $user['rol'];

    switch ($user['rol']) {
        case 'admin': header('Location: /admin/index.php'); break;
        case 'empleado': header('Location: /empleado/dashboard.php'); break;
        default: header('Location: /cliente/dashboard.php'); break;
    }
    exit;
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Error de autenticación. Inténtalo nuevamente.';
    header('Location: /auth/login.php');
    exit;
}