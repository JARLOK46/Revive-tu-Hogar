<?php
require_once __DIR__.'/../../app/config/session.php';
require_once __DIR__.'/../../app/config/db.php';

// Restringir a solicitudes locales por seguridad básica
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($remote, ['127.0.0.1', '::1'])) {
    http_response_code(403);
    exit('Acceso solo desde localhost');
}

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Error de conexión a la base de datos.');
}

// CSRF
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = bin2hex(random_bytes(16));
    $_SESSION['csrf'] = $token;
    $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Crear administrador</title>
      <link rel="stylesheet" href="../assets/css/styles.css">
    </head>
    <body>
      <a class="back-link" href="/index.php">← Inicio</a>
      <div class="container section">
        <div class="card" style="max-width:520px;margin:40px auto">
          <h2>Crear usuario administrador</h2>
          <?php if ($flash): ?>
            <div class="alert" role="status"><?php echo e($flash); ?></div>
          <?php endif; ?>
          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo e($token); ?>">
            <div class="form-grid">
              <input class="input" name="email" type="email" placeholder="Correo del admin" required>
              <input class="input" name="nombre_usuario" placeholder="Nombre de usuario (opcional)">
            </div>
            <div style="display:grid;gap:12px;margin-top:12px">
              <input class="input" name="password" type="password" placeholder="Contraseña (mín. 8)" required>
              <input class="input" name="password2" type="password" placeholder="Repetir contraseña" required>
              <button class="btn" type="submit">Crear admin</button>
            </div>
          </form>
          <p style="margin-top:8px;font-size:14px;color:#666">Solo accesible desde localhost. El admin puede iniciar sesión en <strong>/auth/login.php</strong>.</p>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
        http_response_code(400);
        exit('CSRF inválido.');
    }

    $email = trim($_POST['email'] ?? '');
    $nombre_usuario = trim($_POST['nombre_usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = 'Correo inválido.';
        header('Location: /auth/create_admin.php');
        exit;
    }
    if ($password !== $password2 || strlen($password) < 8) {
        $_SESSION['flash'] = 'La contraseña debe coincidir y tener al menos 8 caracteres.';
        header('Location: /auth/create_admin.php');
        exit;
    }

    try {
        // Verificar si ya existe un admin con ese correo
        $chk = $pdo->prepare('SELECT id FROM usuarios WHERE correo_electronico = ? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetchColumn()) {
            $_SESSION['flash'] = 'Ya existe un usuario con ese correo.';
            header('Location: /auth/create_admin.php');
            exit;
        }

        // Derivar nombre_usuario si no se especifica y asegurar unicidad
        if ($nombre_usuario === '') {
            $base = preg_replace('/[^a-zA-Z0-9_\-]/', '', strstr($email, '@', true) ?: 'admin');
            if ($base === '') { $base = 'admin'; }
            $nombre_usuario = $base;
        }
        $checkUserStmt = $pdo->prepare('SELECT 1 FROM usuarios WHERE nombre_usuario = ? LIMIT 1');
        $suf = 0; $original = $nombre_usuario;
        while (true) {
            $checkUserStmt->execute([$nombre_usuario]);
            if (!$checkUserStmt->fetchColumn()) { break; }
            $suf++; $nombre_usuario = $original . $suf;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $st = $pdo->prepare('INSERT INTO usuarios (nombre_usuario, contrasena_hash, rol, correo_electronico, fecha_registro) VALUES (?,?,?,?, NOW())');
        $st->execute([$nombre_usuario, $hash, 'admin', $email]);

        $_SESSION['flash'] = 'Usuario admin creado correctamente. Ahora puedes iniciar sesión.';
        header('Location: /auth/create_admin.php');
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Error al crear admin: '. e($e->getMessage()));
    }
}