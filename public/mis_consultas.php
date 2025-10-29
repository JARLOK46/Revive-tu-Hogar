<?php
require_once '../app/config/session.php';
require_once '../app/config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php?redirect=' . urlencode('/mis_consultas.php'));
    exit;
}

$usuario_id = $_SESSION['user_id'];

// Verificar que PDO esté disponible
if (!$pdo) {
    error_log("PDO no está disponible en mis_consultas.php");
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}

// Obtener información del usuario
$stmt_usuario = $pdo->prepare("SELECT nombre_usuario, correo_electronico FROM usuarios WHERE id = ?");
if (!$stmt_usuario) {
    error_log("Error preparando consulta de usuario: " . implode(", ", $pdo->errorInfo()));
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}
$stmt_usuario->execute([$usuario_id]);
$usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);

if (!$usuario || !is_array($usuario)) {
    error_log("Usuario no encontrado o datos inválidos para ID: " . $usuario_id);
    session_destroy();
    header('Location: /auth/login.php');
    exit;
}

// Obtener consultas del usuario
try {
    $stmt = $pdo->prepare("
        SELECT id, asunto, mensaje, respuesta, estado, created_at, updated_at 
        FROM consultas 
        WHERE usuario_id = ? 
        ORDER BY created_at DESC
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparando consulta de consultas: " . implode(", ", $pdo->errorInfo()));
    }
    
    $stmt->execute([$usuario_id]);
    $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Asegurar que $consultas sea siempre un array
    if (!is_array($consultas)) {
        error_log("Error: fetchAll() no devolvió un array para usuario ID: " . $usuario_id);
        $consultas = [];
    }
} catch (Exception $e) {
    error_log("Error obteniendo consultas: " . $e->getMessage());
    $consultas = [];
}

// Verificar si hay una nueva consulta para mostrar mensaje de éxito
$nueva_consulta = $_GET['nueva'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mis Consultas - Revive tu Hogar</title>
  <link rel="icon" type="image/x-icon" href="/assets/img/logo.jpg">
  <link rel="stylesheet" href="assets/css/styles.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: #333;
      background: var(--hueso, #FAF9F6);
      min-height: 100vh;
    }

    .navbar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 1rem 0;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
      transition: all 0.3s ease;
      border-bottom: 1px solid rgba(184, 149, 111, 0.1);
    }

    .navbar.scrolled {
      background: rgba(255, 255, 255, 0.98);
      box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
    }

    .nav-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .logo {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      color: #333;
      font-weight: 700;
      font-size: 1.5rem;
    }

    .logo img {
      width: 40px;
      height: 40px;
      border-radius: 8px;
    }

    .nav-links {
      display: flex;
      list-style: none;
      gap: 2rem;
      align-items: center;
    }

    .nav-links a {
      text-decoration: none;
      color: #333;
      font-weight: 500;
      transition: color 0.3s ease;
      position: relative;
    }

    .nav-links a:hover {
      color: #b8956f;
    }

    .user-menu {
      position: relative;
    }

    .user-button {
      background: #b8956f;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 25px;
      cursor: pointer;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .user-button:hover {
      background: #a08660;
      transform: translateY(-1px);
    }

    .main-content {
      margin-top: 100px;
      padding: 2rem 0;
      min-height: calc(100vh - 200px);
    }

    .container {
      max-width: 1000px;
      margin: 0 auto;
      padding: 0 2rem;
    }

    .page-header {
      text-align: center;
      margin-bottom: 3rem;
    }

    .page-header h1 {
      font-size: 2.5rem;
      color: #333;
      margin-bottom: 1rem;
      font-weight: 700;
    }

    .page-header p {
      font-size: 1.1rem;
      color: #666;
      max-width: 600px;
      margin: 0 auto;
    }

    .alert {
      padding: 1rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
      font-weight: 500;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .actions-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .btn {
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .btn-primary {
      background: #b8956f;
      color: white;
    }

    .btn-primary:hover {
      background: #a08660;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(184, 149, 111, 0.3);
    }

    .consultas-grid {
      display: grid;
      gap: 1.5rem;
    }

    .consulta-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
      border-left: 4px solid #b8956f;
    }

    .consulta-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .consulta-card.nueva {
      border-left-color: #28a745;
      background: linear-gradient(135deg, #ffffff 0%, #f8fff9 100%);
    }

    .consulta-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 1rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .consulta-title {
      font-size: 1.2rem;
      font-weight: 700;
      color: #333;
      margin: 0;
    }

    .estado-badge {
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      text-transform: uppercase;
    }

    .estado-pendiente {
      background: #fff3cd;
      color: #856404;
    }

    .estado-respondida {
      background: #d4edda;
      color: #155724;
    }

    .estado-cerrada {
      background: #f8d7da;
      color: #721c24;
    }

    .consulta-meta {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
      font-size: 0.9rem;
      color: #666;
      flex-wrap: wrap;
    }

    .consulta-content {
      margin-bottom: 1rem;
    }

    .consulta-mensaje {
      background: #f8f9fa;
      padding: 1rem;
      border-radius: 8px;
      border-left: 3px solid #b8956f;
      margin-bottom: 1rem;
    }

    .consulta-respuesta {
      background: #e8f5e8;
      padding: 1rem;
      border-radius: 8px;
      border-left: 3px solid #28a745;
    }

    .consulta-respuesta h4 {
      color: #155724;
      margin-bottom: 0.5rem;
      font-size: 1rem;
    }

    .empty-state {
      text-align: center;
      padding: 3rem;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .empty-state h3 {
      color: #666;
      margin-bottom: 1rem;
      font-size: 1.5rem;
    }

    .empty-state p {
      color: #999;
      margin-bottom: 2rem;
    }

    @media (max-width: 768px) {
      .nav-container {
        padding: 0 1rem;
      }

      .nav-links {
        gap: 1rem;
      }

      .container {
        padding: 0 1rem;
      }

      .page-header h1 {
        font-size: 2rem;
      }

      .actions-bar {
        flex-direction: column;
        align-items: stretch;
      }

      .consulta-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .consulta-meta {
        flex-direction: column;
        gap: 0.5rem;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="nav-container">
      <a href="/" class="logo">
        <img src="/assets/img/logo.jpg" alt="Revive tu Hogar">
        <span>Revive tu Hogar</span>
      </a>
      <ul class="nav-links">
        <li><a href="/">Inicio</a></li>
        <li><a href="/servicios.php">Servicios</a></li>
        <li><a href="/planes.php">Planes</a></li>
        <li><a href="/proyectos.php">Proyectos</a></li>
        <li><a href="/proceso.php">Proceso</a></li>
        <li><a href="/contacto.php">Contacto</a></li>
        <li class="user-menu">
          <button class="user-button" onclick="toggleUserMenu()">
            <?php echo htmlspecialchars($usuario['nombre_usuario']); ?>
          </button>
        </li>
      </ul>
    </div>
  </nav>

  <main class="main-content">
    <div class="container">
      <div class="page-header">
        <h1>Mis Consultas</h1>
        <p>Aquí puedes ver todas tus consultas enviadas y las respuestas de nuestro equipo.</p>
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

      <div class="actions-bar">
        <div>
          <span style="color: #666; font-weight: 500;">
            Total de consultas: <?php echo count($consultas); ?>
          </span>
        </div>
        <a href="/contacto.php" class="btn btn-primary">
          ➕ Nueva Consulta
        </a>
      </div>

      <?php if (empty($consultas)): ?>
        <div class="empty-state">
          <h3>No tienes consultas aún</h3>
          <p>Cuando envíes tu primera consulta, aparecerá aquí junto con las respuestas de nuestro equipo.</p>
          <a href="/contacto.php" class="btn btn-primary">Enviar mi primera consulta</a>
        </div>
      <?php else: ?>
        <div class="consultas-grid">
          <?php foreach ($consultas as $consulta): ?>
            <div class="consulta-card <?php echo ($nueva_consulta && $consulta['id'] == $nueva_consulta) ? 'nueva' : ''; ?>">
              <div class="consulta-header">
                <h3 class="consulta-title"><?php echo htmlspecialchars($consulta['asunto']); ?></h3>
                <span class="estado-badge estado-<?php echo $consulta['estado']; ?>">
                  <?php echo ucfirst($consulta['estado']); ?>
                </span>
              </div>

              <div class="consulta-meta">
                <span>📅 Enviada: <?php echo date('d/m/Y H:i', strtotime($consulta['created_at'])); ?></span>
                <?php if ($consulta['updated_at'] !== $consulta['created_at']): ?>
                  <span>🔄 Actualizada: <?php echo date('d/m/Y H:i', strtotime($consulta['updated_at'])); ?></span>
                <?php endif; ?>
              </div>

              <div class="consulta-content">
                <div class="consulta-mensaje">
                  <h4 style="margin-bottom: 0.5rem; color: #333;">Tu consulta:</h4>
                  <p><?php echo nl2br(htmlspecialchars($consulta['mensaje'])); ?></p>
                </div>

                <?php if (!empty($consulta['respuesta'])): ?>
                  <div class="consulta-respuesta">
                    <h4>Respuesta de nuestro equipo:</h4>
                    <p><?php echo nl2br(htmlspecialchars($consulta['respuesta'])); ?></p>
                  </div>
                <?php else: ?>
                  <div style="padding: 1rem; background: #fff3cd; border-radius: 8px; color: #856404;">
                    <p><strong>⏳ Pendiente de respuesta</strong></p>
                    <p style="margin: 0; font-size: 0.9rem;">Nuestro equipo revisará tu consulta y te responderá lo antes posible.</p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
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

    // Auto-scroll to new consultation if present
    <?php if ($nueva_consulta): ?>
      document.addEventListener('DOMContentLoaded', function() {
        const nuevaConsulta = document.querySelector('.consulta-card.nueva');
        if (nuevaConsulta) {
          setTimeout(() => {
            nuevaConsulta.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }, 500);
        }
      });
    <?php endif; ?>
  </script>
</body>
</html>