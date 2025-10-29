<?php
require_once __DIR__.'/../../app/config/session.php';
$token = bin2hex(random_bytes(16));
$_SESSION['csrf'] = $token;
$flash = $_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Registro - Revive tu Hogar</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    body {
      background: #ffffff;
      min-height: 100vh;
    }
    .register-card {
      background: rgba(250, 249, 246, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(140, 106, 79, 0.15);
      border: 1px solid rgba(210, 180, 140, 0.3);
      padding: 32px;
      max-width: 520px;
      margin: 40px auto;
    }
    .register-title {
      text-align: center;
      margin-bottom: 24px;
      color: #2C2C2C;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
    }
    .input-group {
      position: relative;
      margin-bottom: 16px;
    }
    .input-group i {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #8C6A4F;
      z-index: 1;
    }
    .input-with-icon {
      padding-left: 40px;
      border: 1px solid #D2B48C;
      background-color: #FAF9F6;
      color: #2C2C2C;
    }
    .input-with-icon:focus {
      border-color: #6B705C;
      box-shadow: 0 0 0 2px rgba(107, 112, 92, 0.2);
    }
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 16px;
    }
    .register-btn {
      width: 100%;
      padding: 12px;
      background: #6B705C;
      border: none;
      border-radius: 8px;
      color: #FAF9F6;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 8px;
    }
    .register-btn:hover {
      background: #D2B48C;
      color: #2C2C2C;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(107, 112, 92, 0.3);
    }
    .login-link {
      text-align: center;
      margin-top: 20px;
      color: #8C6A4F;
    }
    .login-link a {
      color: #6B705C;
      text-decoration: none;
      font-weight: 600;
    }
    .login-link a:hover {
      color: #D2B48C;
      text-decoration: underline;
    }
    @media(max-width: 600px) {
      .form-row {
        grid-template-columns: 1fr;
      }
      .register-card {
        margin: 20px;
        padding: 24px;
      }
    }
  </style>
</head>
<body>
  <a class="back-link" href="/index.php" aria-label="Regresar al inicio">← Volver</a>
  <div class="container section">
    <div class="register-card">
      <h2 class="register-title">
        <i class="fas fa-user-plus"></i>
        Crear cuenta
      </h2>
      <?php if ($flash): ?>
        <div class="alert error" role="alert"><?php echo htmlspecialchars($flash,ENT_QUOTES,'UTF-8'); ?></div>
      <?php endif; ?>
      <form method="post" action="/auth/registro_post.php">
        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($token,ENT_QUOTES,'UTF-8'); ?>">
        
        <div class="form-row">
          <div class="input-group">
            <i class="fas fa-user"></i>
            <input class="input input-with-icon" name="nombre" placeholder="Nombre" required>
          </div>
          <div class="input-group">
            <i class="fas fa-user"></i>
            <input class="input input-with-icon" name="apellido" placeholder="Apellido" required>
          </div>
        </div>
        
        <div class="input-group">
          <i class="fas fa-envelope"></i>
          <input class="input input-with-icon" name="email" type="email" placeholder="Email" required>
        </div>
        
        <div class="input-group">
          <i class="fas fa-lock"></i>
          <input class="input input-with-icon" name="password" type="password" placeholder="Contraseña" required>
        </div>
        
        <div class="input-group">
          <i class="fas fa-phone"></i>
          <input class="input input-with-icon" name="telefono" placeholder="Teléfono">
        </div>
        
        <button class="register-btn" type="submit">
          <i class="fas fa-user-plus"></i> Registrarme
        </button>
      </form>
      
      <p class="login-link">¿Ya tienes cuenta? <a href="/auth/login.php">Inicia sesión</a></p>
    </div>
  </div>
</body>
</html>