<?php
require_once __DIR__.'/../app/config/session.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Servicios - Revive tu Hogar</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <link rel="icon" href="/assets/img/logo.jpg" type="image/jpeg">
</head>
<body>
  <div id="progressBar"></div>
  <header class="navbar">
    <div class="container navbar-inner">
      <a class="logo" href="/" aria-label="Inicio"><img src="/assets/img/logo.jpg" alt="Logo Revive tu Hogar" class="logo-img" style="height:28px;width:auto;object-fit:cover;border-radius:4px;margin-right:8px;vertical-align:middle;" /><span>Revive tu Hogar</span></a>
      <nav class="nav-links">
        <a href="/servicios.php" class="badge">Servicios</a>
        <a href="/planes.php">Planes</a>
        <a href="/proyectos.php">Proyectos</a>
        <a href="/proceso.php">Proceso</a>
        <?php
          $dash = '/cliente/dashboard.php';
          if (isset($_SESSION['rol'])) {
            if ($_SESSION['rol'] === 'admin') { $dash = '/admin/index.php'; }
            elseif ($_SESSION['rol'] === 'empleado') { $dash = '/empleado/dashboard.php'; }
          }
          ?>
          <?php if (isset($_SESSION['user_id'])): ?>
            <a class="cta avatar-btn" href="<?php echo $dash; ?>" title="Mi cuenta" aria-label="Mi cuenta">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
              <span class="only-desktop">Mi perfil</span>
            </a>
        <?php else: ?>
            <a class="cta" href="/auth/login.php">Iniciar sesión</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main class="container section">
    <section class="hero-page">
      <h1 class="reveal-left">Nuestros servicios</h1>
      <p class="lead reveal-right" style="transition-delay:60ms">Soluciones integrales de mantenimiento, remodelación y mejora del hogar con un equipo de confianza.</p>
    </section>

    <section class="grid cards stagger reveal" style="margin-top:10px">
      <div class="card reveal-scale" data-3d>
        <h3 class="z-20">Mantenimiento</h3>
        <p class="z-10">Programas preventivos y correctivos para que todo funcione sin sorpresas.</p>
        <ul class="features-list">
          <li>Agenda flexible</li>
          <li>Checklist de revisión</li>
          <li>Reportes fotográficos</li>
        </ul>
      </div>
      <div class="card reveal-scale" data-3d>
        <h3 class="z-20">Renovación</h3>
        <p class="z-10">Rediseñamos tus espacios con materiales y acabados de calidad.</p>
        <ul class="features-list">
          <li>Diseño y presupuesto</li>
          <li>Dirección de obra</li>
          <li>Garantía por trabajos</li>
        </ul>
      </div>
      <div class="card reveal-scale" data-3d>
        <h3 class="z-20">Asesoría</h3>
        <p class="z-10">Consultoría experta para optimizar distribución y estilo.</p>
        <ul class="features-list">
          <li>Planos y moodboards</li>
          <li>Selección de materiales</li>
          <li>Estimación de costos</li>
        </ul>
      </div>
      <div class="card reveal-scale" data-3d>
        <h3 class="z-20">Instalaciones</h3>
        <p class="z-10">Servicios eléctricos, hidráulicos y más con técnicos certificados.</p>
        <ul class="features-list">
          <li>Normativas vigentes</li>
          <li>Pruebas de seguridad</li>
          <li>Entrega documentada</li>
        </ul>
      </div>
      <div class="card reveal-scale" data-3d>
        <h3 class="z-20">Pintura y acabados</h3>
        <p class="z-10">Aplicación profesional para renovar ambientes rápidamente.</p>
        <ul class="features-list">
          <li>Protección de muebles</li>
          <li>Muestras de color</li>
          <li>Garantía de adherencia</li>
        </ul>
      </div>
      <div class="card reveal-scale" data-3d>
        <h3 class="z-20">Carpintería</h3>
        <p class="z-10">Muebles a medida, puertas y reparaciones con excelente terminación.</p>
        <ul class="features-list">
          <li>Medición en sitio</li>
          <li>Diseños personalizados</li>
          <li>Instalación incluida</li>
        </ul>
      </div>
    </section>

    <section class="card reveal" style="margin-top:22px">
      <h2>¿Cómo trabajamos?</h2>
      <ol class="timeline">
        <li>Diagnóstico inicial y definición de alcance.</li>
        <li>Propuesta técnica y económica detallada.</li>
        <li>Ejecución con supervisión y control de calidad.</li>
        <li>Entrega final y garantías.</li>
      </ol>
    </section>

    <section class="section" style="padding-top:28px">
      <h2 class="reveal">¿Cómo funcionan nuestras asesorías?</h2>
      <div class="grid cards reveal stagger" style="margin-top:10px">
        <div class="card" data-3d>
          <h3 class="z-20">Modalidades</h3>
          <p class="z-10 subtle">Nos adaptamos a ti para que el proceso sea sencillo y ágil.</p>
          <ul class="features-list">
            <li>Videollamada 45–60 min</li>
            <li>Chat/WhatsApp para dudas puntuales</li>
            <li>Seguimiento por email</li>
          </ul>
        </div>
        <div class="card" data-3d>
          <h3 class="z-20">Entregables</h3>
          <p class="z-10 subtle">Recibirás materiales claros para tomar decisiones.</p>
          <ul class="features-list">
            <li>Moodboard y paleta de color</li>
            <li>Distribución sugerida del espacio</li>
            <li>Lista de compra (opcional)</li>
          </ul>
        </div>
        <div class="card" data-3d>
          <h3 class="z-20">Flujo de trabajo</h3>
          <ul class="features-list">
            <li>Agenda tu sesión y cuéntanos tu idea</li>
            <li>Reunión remota 45–60 min</li>
            <li>Entregables en 24–72 h</li>
            <li>1–2 rondas de ajustes</li>
          </ul>
        </div>
      </div>
      <div class="cta-block glass reveal" style="margin-top:16px">
        <p>¿Listo para tu asesoría? Elige un plan y empecemos.</p>
        <a class="btn grad shine" href="/planes.php">Ver planes</a>
      </div>
    </section>

    <div class="cta-block glass reveal" style="margin-top:20px">
      <p>¿Te interesa alguno de nuestros servicios? Revisa nuestros planes y pide tu visita técnica.</p>
      <a class="btn grad shine" href="/planes.php">Ver planes</a>
    </div>
  </main>
  <footer class="footer theme-bg">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <a class="logo" href="/"><span class="mark"></span><span>Revive tu Hogar</span></a>
          <p class="subtle">Transformamos espacios con diseño moderno, mantenimiento profesional y asesoría personalizada. Calidad y cercanía en cada proyecto.</p>
          <div class="footer-social">
            <a href="#" class="social-link" aria-label="Facebook">
              <span style="font-size: 14px;">f</span>
            </a>
            <a href="#" class="social-link" aria-label="Instagram">
              <span style="font-size: 14px;">📷</span>
            </a>
            <a href="#" class="social-link" aria-label="WhatsApp">
              <span style="font-size: 14px;">💬</span>
            </a>
          </div>
        </div>
        
        <div class="footer-section">
          <h3>Explora</h3>
          <ul class="footer-links">
            <li><a href="/servicios.php">Servicios</a></li>
            <li><a href="/planes.php">Planes</a></li>
            <li><a href="/proyectos.php">Proyectos</a></li>
            <li><a href="/proceso.php">Proceso</a></li>
          </ul>
        </div>
        
        <div class="footer-section">
          <h3>Servicios</h3>
          <ul class="footer-links">
            <li><a href="/servicios.php#mantenimiento">Mantenimiento</a></li>
            <li><a href="/servicios.php#renovacion">Renovación</a></li>
            <li><a href="/servicios.php#asesoria">Asesoría</a></li>
            <li><a href="/servicios.php#diseno">Diseño</a></li>
          </ul>
        </div>
        
        <div class="footer-section">
          <h3>Contacto</h3>
          <div class="footer-contact">
            <div class="contact-item">
              <div class="contact-icon">@</div>
              <span>hola@revivetuhogar.test</span>
            </div>
            <div class="contact-item">
              <div class="contact-icon">📞</div>
              <span>+57 300 000 0000</span>
            </div>
            <div class="contact-item">
              <div class="contact-icon">📍</div>
              <span>Armenia, Quindío</span>
            </div>
            <div class="contact-item" style="margin-top: 15px;">
              <a href="/contacto.php" style="color: #b8956f; text-decoration: none; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <div class="contact-icon">💬</div>
                <span>Enviar consulta</span>
              </a>
            </div>
          </div>
        </div>
        <div class="footer-section">
          <h3>Legales</h3>
          <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:6px">
            <a class="btn btn-secondary shine" href="/terminos.php">Términos y Condiciones</a>
            <a class="btn grad shine" href="/privacidad.php">Política de Privacidad</a>
          </div>
        </div>
      </div>
      
      <div class="footer-bottom">
        <small>© <?php echo date('Y'); ?> Revive tu Hogar. Todos los derechos reservados.</small>
        <div class="footer-social" style="display: none;"> <!-- Hidden on mobile, shown via CSS -->
          <small style="color: #999;">Síguenos en redes sociales</small>
        </div>
      </div>
    </div>
  </footer>
  <script>
    (function(){
      let lastY = window.scrollY;
      let direction = 'down';
      const progressBar = document.getElementById('progressBar');
      const navbar = document.querySelector('.navbar');

      const onScroll = () => {
        const y = window.scrollY;
        direction = y < lastY ? 'up' : 'down';
        lastY = y;
        if (progressBar) {
          const doc = document.documentElement;
          const max = (doc.scrollHeight - window.innerHeight) || 1;
          const p = Math.min(1, Math.max(0, y / max));
          progressBar.style.transform = `scaleX(${p})`;
        }
        if (navbar) {
          if (y > 8) navbar.classList.add('scrolled'); else navbar.classList.remove('scrolled');
        }
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();

      const els = document.querySelectorAll('.reveal, .reveal-left, .reveal-right, .reveal-scale');
      const io = new IntersectionObserver((entries)=>{
        entries.forEach(entry=>{
          if(entry.isIntersecting){
            entry.target.classList.add('in-view');
          } else {
            if(direction === 'up'){
              entry.target.classList.remove('in-view');
            }
          }
        })
      }, { threshold: 0.1, rootMargin: '0px 0px -22% 0px' });
      els.forEach(el=>{
        io.observe(el);
        if(el.classList.contains('stagger')){
          Array.from(el.children).forEach((child,i)=>{
            child.style.transitionDelay = `${i*60}ms`;
          });
        }
      });

      // Nuevo efecto 3D por CSS variables
      const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
      document.querySelectorAll('[data-3d]').forEach(el=>{
        const maxTilt = 10;
        const perspective = 900;
        const scaleOn = 1.02;
        el.style.setProperty('--persp', perspective + 'px');
        let rafId = 0;
        const setVars = (rx, ry, s) => {
          el.style.setProperty('--rx', rx + 'deg');
          el.style.setProperty('--ry', ry + 'deg');
          el.style.setProperty('--scale', s);
        };
        el.addEventListener('mousemove', (e)=>{
          const rect = el.getBoundingClientRect();
          const px = (e.clientX - rect.left) / rect.width;
          const py = (e.clientY - rect.top) / rect.height;
          const ry = clamp((px - 0.5) * (maxTilt*2), -maxTilt, maxTilt);
          const rx = clamp((0.5 - py) * (maxTilt*2), -maxTilt, maxTilt);
          cancelAnimationFrame(rafId);
          rafId = requestAnimationFrame(()=> setVars(rx, ry, scaleOn));
        });
        el.addEventListener('mouseleave', ()=>{
          cancelAnimationFrame(rafId);
          el.style.transition = 'transform .25s ease';
          setVars(0, 0, 1);
          setTimeout(()=>{ el.style.transition=''; }, 260);
        });
        el.addEventListener('mouseenter', ()=>{ el.style.willChange = 'transform'; });
      });
    })();
  </script>

  <!-- Chat flotante n8n -->
  <?php if (isset($_SESSION['user_id'])): ?>
    <div id="n8n-chat"></div>
  <?php else: ?>
    <!-- Botón de chat para usuarios no autenticados -->
    <div id="chat-auth-prompt" style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
      <div class="chat-auth-button" onclick="toggleAuthModal()" style="
        background: #b8956f;
        border: none;
        box-shadow: 0 4px 12px rgba(184, 149, 111, 0.4);
        transition: all 0.3s ease;
        border-radius: 50%;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        cursor: pointer;
        font-size: 24px;
      ">
        💬
      </div>
    </div>
    
    <!-- Modal de autenticación -->
    <div id="auth-modal" style="
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1001;
      align-items: center;
      justify-content: center;
    ">
      <div style="
        background: #f9f7f4;
        border-radius: 16px;
        padding: 30px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        border: 1px solid #d4b896;
      ">
        <h3 style="color: #5d4e37; margin-bottom: 15px; font-size: 24px;">Chat IA</h3>
        <p style="color: #5d4e37; margin-bottom: 25px; line-height: 1.5;">Para usar nuestro asistente virtual, necesitas iniciar sesión o crear una cuenta.</p>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
          <a href="/auth/login.php" style="
            background: #b8956f;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s ease;
            display: inline-block;
          " onmouseover="this.style.background='#a6845e'" onmouseout="this.style.background='#b8956f'">Iniciar Sesión</a>
          <a href="/auth/registro.php" style="
            background: transparent;
            color: #b8956f;
            padding: 12px 24px;
            border: 2px solid #b8956f;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-block;
          " onmouseover="this.style.background='#b8956f'; this.style.color='white'" onmouseout="this.style.background='transparent'; this.style.color='#b8956f'">Registrarse</a>
        </div>
        <button onclick="toggleAuthModal()" style="
          background: none;
          border: none;
          color: #999;
          margin-top: 20px;
          cursor: pointer;
          font-size: 14px;
        ">Cerrar</button>
      </div>
    </div>
    
    <script>
      function toggleAuthModal() {
        const modal = document.getElementById('auth-modal');
        if (modal.style.display === 'none' || modal.style.display === '') {
          modal.style.display = 'flex';
        } else {
          modal.style.display = 'none';
        }
      }
      
      // Cerrar modal al hacer clic fuera de él
      document.getElementById('auth-modal').addEventListener('click', function(e) {
        if (e.target === this) {
          toggleAuthModal();
        }
      });
    </script>
  <?php endif; ?>
  
  <link href="https://cdn.jsdelivr.net/npm/@n8n/chat/dist/style.css" rel="stylesheet" />
  
  <!-- Estilos personalizados para el chat n8n -->
   <style>
     /* Personalización del botón flotante del chat */
     .chat-window-toggle {
       background: #b8956f !important;
       border: none !important;
       box-shadow: 0 4px 12px rgba(184, 149, 111, 0.4) !important;
       transition: all 0.3s ease !important;
       border-radius: 50% !important;
       width: 60px !important;
       height: 60px !important;
       display: flex !important;
       align-items: center !important;
       justify-content: center !important;
       color: white !important;
     }
     
     .chat-window-toggle:hover {
       background: #a6845e !important;
       transform: scale(1.05) !important;
     }
     
     .chat-window-toggle svg {
       color: white !important;
     }
    
    /* Personalización de la ventana del chat */
     .chat-window {
       border-radius: 16px !important;
       box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15) !important;
       border: 1px solid #d4b896 !important;
       background: #f9f7f4 !important;
     }
     
     /* Personalización del header */
     .chat-header {
       background: linear-gradient(135deg, #b8956f, #a6845e) !important;
       border-radius: 16px 16px 0 0 !important;
       padding: 20px !important;
       border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
     }
     
     .chat-header h1 {
       color: white !important;
       font-weight: 600 !important;
       font-size: 18px !important;
       margin: 0 !important;
     }
     
     .chat-header p {
       color: rgba(255, 255, 255, 0.9) !important;
       font-size: 14px !important;
       margin: 5px 0 0 0 !important;
     }
    
    /* Personalización del cuerpo del chat */
     .chat-body {
       background: #f9f7f4 !important;
       padding: 16px !important;
     }
     
     /* Personalización de los mensajes */
     .chat-message-from-user {
       background: transparent !important;
       border: none !important;
       box-shadow: none !important;
     }
     
     .chat-message-from-bot {
       background: transparent !important;
       border: none !important;
       box-shadow: none !important;
     }
     
     .chat-message-from-user .chat-message-markdown {
       background: #b8956f !important;
       color: white !important;
       border-radius: 18px 18px 4px 18px !important;
       padding: 12px 16px !important;
     }
     
     .chat-message-from-bot .chat-message-markdown {
       background: #f4f1ed !important;
       color: #5d4e37 !important;
       border-radius: 18px 18px 18px 4px !important;
       border: 1px solid #d4b896 !important;
       padding: 12px 16px !important;
     }
    
    /* Personalización del área de input */
     .chat-input-container {
       padding: 16px !important;
       background: #f9f7f4 !important;
       border-top: 1px solid #d4b896 !important;
       display: flex !important;
       gap: 8px !important;
       align-items: flex-end !important;
     }
     
     textarea[data-test-id="chat-input"] {
       border: 2px solid #d4b896 !important;
       border-radius: 24px !important;
       padding: 12px 16px !important;
       font-size: 14px !important;
       transition: border-color 0.2s ease !important;
       resize: none !important;
       background: #fefcfa !important;
       flex: 1 !important;
       color: #5d4e37 !important;
     }
     
     textarea[data-test-id="chat-input"]:focus {
       border-color: #b8956f !important;
       outline: none !important;
       box-shadow: 0 0 0 3px rgba(184, 149, 111, 0.2) !important;
     }
     
     .chat-input-send-button {
       background: #b8956f !important;
       border: none !important;
       border-radius: 50% !important;
       width: 40px !important;
       height: 40px !important;
       display: flex !important;
       align-items: center !important;
       justify-content: center !important;
       transition: background 0.2s ease !important;
       color: white !important;
     }
     
     .chat-input-send-button:hover:not(:disabled) {
       background: #a6845e !important;
     }
     
     .chat-input-send-button:disabled {
       background: #ccc !important;
       cursor: not-allowed !important;
     }
     
     .chat-input-send-button svg {
       color: white !important;
     }
    
    /* Personalización del scroll */
    .n8n-chat .n8n-chat-messages::-webkit-scrollbar {
      width: 6px !important;
    }
    
    .n8n-chat .n8n-chat-messages::-webkit-scrollbar-track {
      background: #f1f1f1 !important;
      border-radius: 3px !important;
    }
    
    .n8n-chat .n8n-chat-messages::-webkit-scrollbar-thumb {
      background: #d4b896 !important;
      border-radius: 3px !important;
    }
    
    .n8n-chat .n8n-chat-messages::-webkit-scrollbar-thumb:hover {
      background: #c9a882 !important;
    }
    
    /* Animaciones suaves */
    .n8n-chat .n8n-chat-message {
      animation: slideIn 0.3s ease-out !important;
    }
    
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    /* Responsive design */
    @media (max-width: 480px) {
      .n8n-chat .n8n-chat-window {
        width: 100vw !important;
        height: 100vh !important;
        border-radius: 0 !important;
        bottom: 0 !important;
        right: 0 !important;
      }
      
      .n8n-chat .n8n-chat-header {
        border-radius: 0 !important;
      }
    }
  </style>
  <?php if (isset($_SESSION['user_id'])): ?>
  <script type="module">
     import { createChat } from 'https://cdn.jsdelivr.net/npm/@n8n/chat/chat.bundle.es.js';
     
     // Crear sessionId único para el usuario (no por página)
     const userSessionId = '<?php echo $_SESSION['user_id']; ?>';
     const globalStorageKey = 'revive_chat_global_' + userSessionId;
     const globalMessagesKey = 'revive_chat_messages_global_' + userSessionId;
     
     // Sistema mejorado de almacenamiento de mensajes
     const ChatStorage = {
       // Guardar mensajes en localStorage con timestamp
       saveMessages: function(messages) {
         try {
           const messageData = {
             messages: messages,
             timestamp: Date.now(),
             userId: userSessionId
           };
           localStorage.setItem(globalMessagesKey, JSON.stringify(messageData));
           console.log('💾 Mensajes guardados globalmente:', messages.length);
         } catch (e) {
           console.error('❌ Error guardando mensajes:', e);
         }
       },
       
       // Cargar mensajes desde localStorage
       loadMessages: function() {
         try {
           const saved = localStorage.getItem(globalMessagesKey);
           if (!saved) {
             console.log('📭 No hay mensajes guardados');
             return [];
           }
           
           const messageData = JSON.parse(saved);
           const messages = messageData.messages || [];
           console.log('📬 Mensajes cargados globalmente:', messages.length);
           return messages;
         } catch (e) {
           console.error('❌ Error cargando mensajes:', e);
           return [];
         }
       },
       
       // Agregar un nuevo mensaje al storage
       addMessage: function(message) {
         const currentMessages = this.loadMessages();
         
         // Evitar duplicados basados en timestamp y contenido
         const isDuplicate = currentMessages.some(msg => 
           msg.text === message.text && 
           Math.abs((msg.timestamp || 0) - (message.timestamp || Date.now())) < 1000
         );
         
         if (!isDuplicate) {
           message.timestamp = message.timestamp || Date.now();
           currentMessages.push(message);
           this.saveMessages(currentMessages);
           console.log('➕ Nuevo mensaje agregado:', message.text?.substring(0, 50) + '...');
         }
       },
       
       // Limpiar mensajes antiguos (opcional)
       clearOldMessages: function(daysOld = 7) {
         const messages = this.loadMessages();
         const cutoffTime = Date.now() - (daysOld * 24 * 60 * 60 * 1000);
         const filteredMessages = messages.filter(msg => 
           (msg.timestamp || 0) > cutoffTime
         );
         
         if (filteredMessages.length !== messages.length) {
           this.saveMessages(filteredMessages);
           console.log('🧹 Mensajes antiguos limpiados');
         }
       }
     };
     
     // --- Sistema de HISTORIALES (múltiples sesiones por usuario) ---
    const SESS_LIST_KEY = 'revive_chat_sessions_' + userSessionId;
    const SESS_CURR_KEY = 'revive_chat_current_session_' + userSessionId;
    const SESS_MSG_PREFIX = 'revive_chat_messages_' + userSessionId + '_';

    const ChatSessions = {
      loadSessions() { try { return JSON.parse(localStorage.getItem(SESS_LIST_KEY)) || []; } catch { return []; } },
      saveSessions(list) { try { localStorage.setItem(SESS_LIST_KEY, JSON.stringify(list)); } catch {} },
      ensureActiveSession() {
        let id = localStorage.getItem(SESS_CURR_KEY);
        const sessions = this.loadSessions();
        if (!id || !sessions.find(s => s.id === id)) {
          const migrated = (typeof ChatStorage !== 'undefined' && ChatStorage.loadMessages) ? (ChatStorage.loadMessages() || []) : [];
          id = 's_' + Date.now();
          const title = 'Chat ' + (sessions.length + 1);
          sessions.push({ id, title, createdAt: Date.now(), updatedAt: Date.now() });
          this.saveSessions(sessions);
          localStorage.setItem(SESS_CURR_KEY, id);
          if (migrated.length) this.saveMessages(id, migrated);
        }
        window.currentChatSessionId = id;
        return id;
      },
      setCurrentSession(id){ localStorage.setItem(SESS_CURR_KEY, id); window.currentChatSessionId = id; },
      getCurrentSession(){ return localStorage.getItem(SESS_CURR_KEY); },
      getMessages(id){ try { return JSON.parse(localStorage.getItem(SESS_MSG_PREFIX + id)) || []; } catch { return []; } },
      saveMessages(id, messages){ try { localStorage.setItem(SESS_MSG_PREFIX + id, JSON.stringify(messages)); const sessions = this.loadSessions(); const s = sessions.find(x=>x.id===id); if (s){ s.updatedAt = Date.now(); this.saveSessions(sessions);} } catch {} },
      addMessage(id, message){ const msgs = this.getMessages(id); const m = { text: String(message.text||''), sender: String(message.sender||'user'), timestamp: message.timestamp||Date.now() }; if (!m.text.trim()) return; msgs.push(m); this.saveMessages(id, msgs); },
      createNewSession(title){ const sessions = this.loadSessions(); const id = 's_' + Date.now(); sessions.push({ id, title: title||'Chat '+(sessions.length+1), createdAt: Date.now(), updatedAt: Date.now() }); this.saveSessions(sessions); this.saveMessages(id, []); this.setCurrentSession(id); return id; }
    };

    (function(){ if (!window.currentChatSessionId) ChatSessions.ensureActiveSession(); try { const _origAdd = ChatStorage.addMessage.bind(ChatStorage); ChatStorage.addMessage = function(message){ try{ _origAdd(message);}catch(e){} try{ const sid = window.currentChatSessionId || ChatSessions.ensureActiveSession(); ChatSessions.addMessage(sid, message);}catch(e){ console.warn('ChatSessions.addMessage error', e);} }; } catch(e){ console.warn('Monkey-patch ChatStorage.addMessage falló', e);} })();

    function initChatSessionUI(){ try{ const controlsId='chat-session-controls'; if(document.getElementById(controlsId)) return; const c=document.createElement('div'); c.id=controlsId; c.className='chat-session-controls'; c.innerHTML=`<select id="chat-session-select" class="chat-session-select"></select><button id="chat-session-new" class="chat-session-new">Nuevo chat</button>`;

  (function(){
    function tryInsert(){
      const roots = [];
      const host = document.querySelector('#n8n-chat');
      if (host){
        roots.push(host);
        if (host.shadowRoot) roots.push(host.shadowRoot);
      }
      roots.push(document);
      for (const root of roots){
        if (!root || !root.querySelector) continue;
        const header = root.querySelector('.n8n-chat-header') || root.querySelector('.n8n-chat .n8n-chat-header') || root.querySelector('[class*="chat-header"]');
        if (header){
          c.classList.add('chat-session-controls--inline');
          c.style.position='static';
          c.style.background='transparent';
          c.style.border='0';
          c.style.boxShadow='none';
          c.style.padding='0';
          c.style.marginLeft='auto';
          header.style.display = header.style.display || 'flex';
          header.style.alignItems='center';
          header.appendChild(c);
          return true;
        }
      }
      return false;
    }
    if (!tryInsert()){
      let attempts=0;
      const iv=setInterval(()=>{
        attempts++;
        if (tryInsert() || attempts>=20){
          clearInterval(iv);
          if (!c.parentNode){
            const host = document.querySelector('#n8n-chat') || document.querySelector('.n8n-chat') || document.body;
            try{ const cs = host && host.nodeType===1 ? getComputedStyle(host) : null; if (cs && cs.position==='static'){ host.style.position='relative'; } }catch(_){ }
            c.classList.remove('chat-session-controls--inline');
            c.classList.add('chat-session-controls--overlay');
            c.style.position='absolute';
            c.style.top='8px';
            c.style.right='12px';
            c.style.background='#f9f7f4';
            c.style.border='1px solid #d4b896';
            c.style.boxShadow='0 8px 24px rgba(0,0,0,0.12)';
            c.style.padding='6px';
            c.style.marginLeft='0';
            host.appendChild(c);
            try{ const rootsToWatch=[document, host]; if (host && host.shadowRoot) rootsToWatch.push(host.shadowRoot); const mo=new MutationObserver(()=>{ if (tryInsert()){ try{ mo.disconnect(); }catch(_){ } } }); rootsToWatch.forEach(r=>{ if(r && r.nodeType===1){ mo.observe(r,{childList:true,subtree:true}); } }); }catch(_){ }
          }
        }
      },250);
    }
  })();

  const styleId='chat-session-controls-style'; if(!document.getElementById(styleId)){ const st=document.createElement('style'); st.id=styleId; st.textContent=`.chat-session-controls{position:fixed; right:24px; bottom:96px; z-index:2147483647; background:#f9f7f4; border:1px solid #d4b896; border-radius:12px; padding:8px; display:flex; gap:8px; align-items:center; box-shadow:0 8px 24px rgba(0,0,0,0.12)}.chat-session-select{appearance:none; padding:6px 8px; border:1px solid #d4b896; border-radius:8px; background:#fff; color:#5d4e37}.chat-session-new{padding:6px 10px; border-radius:8px; background:#b8956f; color:#fff; border:1px solid #a6845e; cursor:pointer}.chat-session-new:hover{background:#a6845e}`; document.head.appendChild(st);} const select=c.querySelector('#chat-session-select'); function refreshOptions(){ const sessions=ChatSessions.loadSessions(); const current=ChatSessions.ensureActiveSession(); select.innerHTML=sessions.map(s=>`<option value="${s.id}" ${s.id===current?'selected':''}>${s.title}</option>`).join(''); } refreshOptions(); select.addEventListener('change',(e)=>{ const id=e.target.value; ChatSessions.setCurrentSession(id); const msgs=ChatSessions.getMessages(id); const mapped=msgs.map(m=>m.sender==='user'?('Tú: '+m.text):m.text); if(window.chatInstance && window.chatInstance.loadMessages){ window.chatInstance.loadMessages(mapped);} }); c.querySelector('#chat-session-new').addEventListener('click',()=>{ const id=ChatSessions.createNewSession(); refreshOptions(); select.value=id; if(window.chatInstance && window.chatInstance.loadMessages){ window.chatInstance.loadMessages([]);} }); }catch(e){ console.warn('initChatSessionUI error', e);} }

    // Limpiar mensajes antiguos al cargar
    ChatStorage.clearOldMessages();
     
     // Cargar mensajes previos con validación estricta
      let previousMessages = ChatStorage.loadMessages();
      
      // Validación más estricta para n8n
      previousMessages = previousMessages.filter(msg => {
        if (!msg || typeof msg !== 'object') return false;
        if (!msg.text || typeof msg.text !== 'string') return false;
        if (msg.text.trim().length === 0) return false;
        return true;
      }).map(msg => {
        // Formato exacto que espera n8n
        const cleanMessage = {
          text: String(msg.text).trim(),
          sender: String(msg.sender || 'user')
        };
        
        // Verificar que el texto sea válido
        if (cleanMessage.text.length === 0) return null;
        
        return cleanMessage;
      }).filter(msg => msg !== null);
      
      console.log('🔄 Inicializando chat con', previousMessages.length, 'mensajes válidos previos');
      console.log('📋 Mensajes a cargar:', previousMessages);
     
     // Variable para almacenar la instancia del chat
     let chatInstance;
     
     // Configuración del chat con persistencia mejorada
     chatInstance = window.chatInstance = createChat({
       webhookUrl: 'http://localhost:5678/webhook/a889d2ae-2159-402f-b326-5f61e90f602e/chat',
       webhookConfig: {
         method: 'POST',
         headers: {
           'Content-Type': 'application/json'
         }
       },
       sessionId: userSessionId,
       target: '#n8n-chat',
       mode: 'window',
       chatInputKey: 'chatInput',
       chatSessionKey: globalStorageKey,
       loadPreviousSession: true,
       storageKey: globalStorageKey,
       persistSession: true,
       enableFileUpload: false,
       showTypingIndicator: true,
       metadata: {
         sessionId: userSessionId,
         userId: userSessionId,
         userRole: '<?php echo $_SESSION['rol'] ?? 'cliente'; ?>',
         userName: '<?php echo $_SESSION['nombre'] ?? 'Usuario'; ?>',
         currentPage: 'servicios',
         globalSession: true
       },
       showWelcomeScreen: false,
       defaultLanguage: 'es',
       i18n: {
         es: {
           title: 'Chat IA',
           subtitle: "Inicia una conversación. Estamos aquí para ayudarte 24/7 con tus proyectos de renovación.",
           footer: '',
           getStarted: 'Nueva Conversación',
           inputPlaceholder: 'Escribe tu pregunta aquí...',
         },
       },
       enableStreaming: false,
       initialMessages: previousMessages.map(m => m.sender === 'user' ? ('Tú: ' + m.text) : m.text),
       
       // Eventos mejorados para capturar mensajes
       onMessage: function(message) {
         console.log('📨 Mensaje recibido del bot:', message);
         ChatStorage.addMessage({
           ...message,
           sender: 'bot',
           timestamp: Date.now()
         });
       },
       
       onMessageSent: function(message) {
         console.log('📤 Mensaje enviado por usuario:', message);
         ChatStorage.addMessage({
           ...message,
           sender: 'user',
           timestamp: Date.now()
         });
       },
       
       // Evento cuando el chat está listo
      onChatReady: function() {
        console.log('✅ Chat inicializado correctamente');
        initChatSessionUI();
        console.log('📊 Mensajes previos cargados:', previousMessages.length);
        
        // Verificar si los mensajes se cargaron en el DOM
        setTimeout(() => {
          const chatContainer = document.querySelector('#n8n-chat');
          if (chatContainer) {
            const visibleMessages = chatContainer.querySelectorAll('.chat-message, [class*="message"]');
            console.log('👁️ Mensajes visibles en DOM:', visibleMessages.length);
            
            if (previousMessages.length > 0 && visibleMessages.length === 0) {
              console.log('⚠️ PROBLEMA: Hay mensajes guardados pero no se muestran en el chat');
              console.log('🔄 Intentando forzar la recarga de mensajes...');
              
              // Intentar recargar el chat con los mensajes
              if (window.chatInstance && window.chatInstance.loadMessages) {
                window.chatInstance.loadMessages(previousMessages.map(m => m.sender === 'user' ? ('Tú: ' + m.text) : m.text));
              }
            }
          }
        }, 3000);
      }
     });
     
     // Sistema de respaldo para capturar mensajes del DOM
     document.addEventListener('DOMContentLoaded', function() {
        try { initChatSessionUI(); } catch(e) {}

  function restyleUserPrefixedMessages(root) {
    try {
      const scope = root && root.querySelector ? root : document;
      const chatContainer = scope.querySelector('#n8n-chat') || scope;
      const nodes = chatContainer.querySelectorAll('.chat-message, [class*="message"]');
      nodes.forEach(node => {
        if (!(node instanceof Element)) return;
        const markdown = node.querySelector ? node.querySelector('.chat-message-markdown') : null;
        const el = markdown || node;
        const raw = (el.textContent || '').trim();
        if (/^Tú:\s*/.test(raw)) {
          const newText = raw.replace(/^Tú:\s*/,'');
          if (markdown) { markdown.textContent = newText; } else { node.textContent = newText; }
          node.classList.add('chat-message-from-user');
          node.classList.remove('chat-message-from-bot');
        }
      });
    } catch (e) { console.warn('restyleUserPrefixedMessages error', e); }
  }

  // Pase inicial y reintentos breves
  setTimeout(() => restyleUserPrefixedMessages(document), 150);
  const _restyleTimer = setInterval(() => restyleUserPrefixedMessages(document), 1000);
  setTimeout(() => clearInterval(_restyleTimer), 8000);

       console.log('🚀 DOM cargado, configurando observadores...');
       
       // Observador mejorado para cambios en el chat
       const observer = new MutationObserver(function(mutations) {
         mutations.forEach(function(mutation) {
           if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
             // Buscar nuevos mensajes agregados
             mutation.addedNodes.forEach(node => {
               if (node.nodeType === Node.ELEMENT_NODE) {
                 // Verificar si es un mensaje de chat
                 if (node.classList && (node.classList.contains('chat-message') || 
                     node.querySelector && node.querySelector('.chat-message'))) {
                   
                   setTimeout(() => {
                     // Extraer y guardar el mensaje
                     const messageText = node.textContent || node.innerText || '';
                     if (messageText.trim()) {
                       const isUserMessage = node.classList.contains('chat-message-from-user') || 
                                            (node.querySelector && node.querySelector('.chat-message-from-user'));
                       
                       ChatStorage.addMessage({
                         text: messageText.trim(),
                         sender: isUserMessage ? 'user' : 'bot',
                         timestamp: Date.now()
                       });
                     }
                   }, 500);
                 }
               }
             });
           }
         });
       });
       
       // Configurar el observador cuando el chat esté disponible
       const setupObserver = () => {
         const chatContainer = document.querySelector('#n8n-chat');
         if (chatContainer) {
           observer.observe(chatContainer, {
             childList: true,
             subtree: true,
             attributes: false,
             characterData: false
           });
           console.log('👁️ Observador de mensajes configurado');
           return true;
         }
         return false;
       };
       
       // Intentar configurar el observador
       if (!setupObserver()) {
         const checkInterval = setInterval(() => {
           if (setupObserver()) {
             clearInterval(checkInterval);
           }
         }, 1000);
         
         // Timeout de seguridad
         setTimeout(() => {
           clearInterval(checkInterval);
         }, 10000);
       }
     });
     
     // Guardar estado antes de que el usuario salga de la página
     window.addEventListener('beforeunload', function() {
       console.log('💾 Guardando estado del chat antes de salir...');
       // El localStorage ya se guarda automáticamente, pero esto asegura la persistencia
     });
     
     // Exponer funciones globales para debugging
     window.ChatDebug = {
       loadMessages: () => ChatStorage.loadMessages(),
       clearMessages: () => {
         localStorage.removeItem(globalMessagesKey);
         console.log('🗑️ Mensajes limpiados');
       },
       showStats: () => {
         const messages = ChatStorage.loadMessages();
         console.log('📊 Estadísticas del chat:', {
           totalMessages: messages.length,
           userMessages: messages.filter(m => m.sender === 'user').length,
           botMessages: messages.filter(m => m.sender === 'bot').length,
           storageKey: globalMessagesKey
         });
       }
     };
     
   </script>
  <?php endif; ?>

</body>
</html>