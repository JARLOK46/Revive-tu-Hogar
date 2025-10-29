<?php
require_once '../app/config/session.php';
require_once '../app/config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php?redirect=' . urlencode('/contacto.php'));
    exit;
}

// Obtener información del usuario
if (!$pdo) {
    error_log("PDO es null en contacto.php");
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}
$stmt = $pdo->prepare("SELECT nombre_usuario, correo_electronico FROM usuarios WHERE id = ?");
if (!$stmt) {
    error_log("Error preparando consulta de usuario: " . ($pdo ? implode(", ", $pdo->errorInfo()) : "PDO es null"));
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario || !is_array($usuario)) {
    error_log("Usuario no encontrado o datos inválidos para ID: " . $_SESSION['user_id']);
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}

// Obtener mensajes de éxito o error
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contacto - Revive tu Hogar</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <link rel="icon" href="/assets/img/logo.jpg" type="image/jpeg">
  <style>
    /* Estilos específicos para la página de contacto */
    .contact-header {
      text-align: center;
      margin: 4rem 0 3rem 0;
      padding: 0 1rem;
    }

    .contact-header h1 {
      font-size: 2.5rem;
      color: var(--text-primary, #1a1a1a);
      margin-bottom: 1rem;
      font-weight: 700;
    }

    .contact-header p {
      font-size: 1.1rem;
      color: var(--text-secondary, #666);
      max-width: 600px;
      margin: 0 auto;
      line-height: 1.6;
    }

    .alert {
      padding: 1rem 1.5rem;
      border-radius: 8px;
      margin-bottom: 2rem;
      font-weight: 500;
    }

    .alert-success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .contact-form {
      background: white;
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      margin-bottom: 3rem;
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.5rem;
      margin-bottom: 1.5rem;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group.full-width {
      grid-column: 1 / -1;
    }

    label {
      font-weight: 600;
      margin-bottom: 0.5rem;
      color: var(--text-primary, #1a1a1a);
      font-size: 0.95rem;
    }

    input,
    textarea,
    select {
      padding: 0.875rem;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      font-size: 1rem;
      transition: all 0.2s ease;
      font-family: inherit;
    }

    input:focus,
    textarea:focus,
    select:focus {
      outline: none;
      border-color: var(--beige-osc, #D2B48C);
      box-shadow: 0 0 0 3px rgba(210, 180, 140, 0.2);
    }

    textarea {
      resize: vertical;
      min-height: 120px;
      line-height: 1.5;
    }

    .submit-btn {
      background: var(--beige-osc, #D2B48C);
      color: white;
      border: none;
      padding: 1rem 2rem;
      border-radius: 12px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(.34,1.56,.64,1);
      width: 100%;
      font-family: inherit;
      box-shadow: 0 10px 25px rgba(0,0,0,.08);
      position: relative;
      overflow: hidden;
    }

    .submit-btn::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(45deg, transparent 30%, rgba(255,255,255,0.3) 50%, transparent 70%);
      transform: translateX(-100%) skewX(-25deg);
      transition: transform .6s ease;
      z-index: 1;
    }

    .submit-btn:hover {
      background: var(--beige-osc, #D2B48C);
      transform: translateY(-4px) scale(1.05);
      box-shadow: 0 15px 35px rgba(0,0,0,.2), 0 0 20px rgba(107,112,92,0.4);
    }

    .submit-btn:hover::before {
      transform: translateX(100%) skewX(-25deg);
    }

    .submit-btn:active {
      transform: translateY(-2px) scale(1.02);
    }

    .contact-info {
      margin-top: 3rem;
      text-align: center;
      padding: 2rem;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }

    .contact-info h3 {
      margin-bottom: 1rem;
      color: var(--text-primary, #1a1a1a);
      font-weight: 600;
    }

    .contact-info p {
      color: var(--text-secondary, #666);
      margin-bottom: 0.5rem;
    }

    @media (max-width: 768px) {
      .contact-header {
        margin: 2rem 0;
      }

      .contact-header h1 {
        font-size: 2rem;
      }

      .contact-form {
        padding: 1.5rem;
        margin: 0 1rem 2rem 1rem;
      }

      .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
      }

      .contact-info {
        margin: 2rem 1rem 0 1rem;
      }
    }
  </style>
</head>
<body>
  <a href="#main" class="skip-link">Saltar al contenido</a>
  <div id="progressBar"></div>
  <header class="navbar">
    <div class="container navbar-inner">
      <a class="logo" href="/" aria-label="Inicio">
        <img src="/assets/img/logo.jpg" alt="Logo Revive tu Hogar" class="logo-img" style="height:28px;width:auto;object-fit:cover;border-radius:4px;margin-right:8px;vertical-align:middle;" />
        <span>Revive tu Hogar</span>
      </a>
      <nav class="nav-links">
        <a href="/servicios.php">Servicios</a>
        <a href="/planes.php">Planes</a>
        <a href="/proyectos.php">Proyectos</a>
        <a href="/proceso.php">Proceso</a>
        <a href="/contacto.php" class="active">Contacto</a>
        <?php
          $dash = '/cliente/dashboard.php';
          if (isset($_SESSION['rol'])) {
            if ($_SESSION['rol'] === 'admin') { $dash = '/admin/index.php'; }
            elseif ($_SESSION['rol'] === 'empleado') { $dash = '/empleado/dashboard.php'; }
          }
        ?>
        <a class="cta avatar-btn" href="<?php echo $dash; ?>" title="Mi cuenta" aria-label="Mi cuenta">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
          <span class="only-desktop">Mi perfil</span>
        </a>
      </nav>
    </div>
  </header>

  <main id="main" class="main-content">
    <div class="container">
      <div class="contact-header">
        <h1>Contacto</h1>
        <p>¿Tienes alguna consulta sobre nuestros servicios? Envíanos un mensaje y te responderemos lo antes posible.</p>
      </div>

      <?php if ($success_message): ?>
        <div class="alert alert-success">
          <?php echo htmlspecialchars($success_message); ?>
        </div>
      <?php endif; ?>

      <?php if ($error_message): ?>
        <div class="alert alert-error">
          <?php echo htmlspecialchars($error_message); ?>
        </div>
      <?php endif; ?>

      <div class="user-info">
        <h3>Información de contacto</h3>
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre_usuario']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['correo_electronico']); ?></p>
      </div>

      <form class="contact-form" method="POST" action="/procesar_contacto.php">
        <div class="form-row">
          <div class="form-group">
            <label for="asunto">Asunto *</label>
            <select id="asunto" name="asunto" required>
              <option value="">Selecciona un asunto</option>
              <option value="Consulta general">Consulta general</option>
              <option value="Solicitud de cotización">Solicitud de cotización</option>
              <option value="Información sobre servicios">Información sobre servicios</option>
              <option value="Seguimiento de proyecto">Seguimiento de proyecto</option>
              <option value="Reclamo o sugerencia">Reclamo o sugerencia</option>
              <option value="Otro">Otro</option>
            </select>
          </div>
          <div class="form-group">
            <label for="prioridad">Prioridad</label>
            <select id="prioridad" name="prioridad">
              <option value="baja">Baja</option>
              <option value="media" selected>Media</option>
              <option value="alta">Alta</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label for="mensaje">Mensaje *</label>
          <textarea id="mensaje" name="mensaje" placeholder="Describe tu consulta detalladamente..." required></textarea>
        </div>

        <button type="submit" class="submit-btn">Enviar Consulta</button>
      </form>

      <div style="text-align: center; margin-top: 2rem;">
        <p style="color: #666;">
          <a href="/mis_consultas.php" style="color: #b8956f; text-decoration: none; font-weight: 600;">
            Ver mis consultas anteriores →
          </a>
        </p>
      </div>
    </div>
  </main>

  <script>
    // Navbar scroll effect
    window.addEventListener('scroll', () => {
      const navbar = document.querySelector('.navbar');
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });

    // User menu toggle (placeholder)
    function toggleUserMenu() {
      // Implementar menú de usuario si es necesario
      window.location.href = '/dashboard.php';
    }

    // Form validation
    document.querySelector('.contact-form').addEventListener('submit', function(e) {
      const asunto = document.getElementById('asunto').value;
      const mensaje = document.getElementById('mensaje').value;

      if (!asunto || !mensaje.trim()) {
        e.preventDefault();
        alert('Por favor completa todos los campos obligatorios.');
        return;
      }

      if (mensaje.trim().length < 10) {
        e.preventDefault();
        alert('El mensaje debe tener al menos 10 caracteres.');
        return;
      }

      // Deshabilitar botón para evitar envíos múltiples
      const submitBtn = document.querySelector('.submit-btn');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Enviando...';
    });
  </script>
</body>
</html>