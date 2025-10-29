<?php
require_once __DIR__.'/../app/config/session.php';
require_once __DIR__.'/../app/config/db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Política de Privacidad - Revive tu Hogar</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <link rel="icon" href="/assets/img/logo.jpg" type="image/jpeg">
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
        <a href="/contacto.php">Contacto</a>
        <?php
          $dash = '/cliente/dashboard.php';
          if (isset($_SESSION['rol'])) {
            if ($_SESSION['rol'] === 'admin') { $dash = '/admin/index.php'; }
            elseif ($_SESSION['rol'] === 'empleado') { $dash = '/empleado/dashboard.php'; }
          }
          if (isset($_SESSION['user_id'])): ?>
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

  <main id="main" tabindex="-1">
    <section class="hero container theme-bg">
      <div>
        <h1 class="reveal-left">Política de Privacidad</h1>
        <p class="lead reveal-right" style="transition-delay:60ms">Conoce cómo recopilamos, usamos y protegemos tus datos.</p>
      </div>
      <div class="card theme-surface float-y reveal" data-3d style="transition-delay:120ms">
        <strong class="badge">Legal</strong>
        <ul>
          <li>Datos recolectados y uso</li>
          <li>Base legal y retención</li>
          <li>Compartición, seguridad y derechos</li>
        </ul>
      </div>
    </section>

    <section class="section theme-bg">
      <div class="container">
        <div class="card theme-surface reveal">
          <h2 id="datos">1. Datos que recolectamos</h2>
          <p>Recopilamos información necesaria para brindarte nuestros servicios: nombre, correo, teléfono, dirección, información de compra, preferencias y datos técnicos de navegación (IP, dispositivo, navegador) mediante cookies u otras tecnologías.</p>
          <ul>
            <li>Identificación y contacto: nombre, correo, teléfono.</li>
            <li>Transaccionales: pedidos, pagos, historial de servicios.</li>
            <li>Soporte y atención: mensajes, encuestas, tickets.</li>
            <li>Navegación: páginas visitadas, tiempos, interacción, cookies.</li>
          </ul>

          <h2 id="uso" style="margin-top:14px">2. Uso de la información</h2>
          <p>Usamos tus datos para procesar pedidos, gestionar servicios, ofrecer soporte, mejorar la experiencia, enviar comunicaciones relevantes y cumplir obligaciones legales. No utilizamos tu información con fines incompatibles con lo aquí descrito.</p>

          <h2 id="base-legal" style="margin-top:14px">3. Base legal del tratamiento</h2>
          <p>Tratamos tus datos sobre las siguientes bases legales: (i) ejecución de contrato, (ii) cumplimiento de obligaciones legales, (iii) consentimiento para comunicaciones comerciales y el uso de cookies no esenciales, y (iv) interés legítimo para mejorar el servicio y prevenir fraude.</p>

          <h2 id="retencion" style="margin-top:14px">4. Retención y eliminación de datos</h2>
          <p>Conservamos los datos mientras sean necesarios para el fin recogido y de acuerdo con plazos legales (por ejemplo, contables y fiscales). Posteriormente, se anonimizan o eliminan de forma segura. Puedes pedir la eliminación cuando no exista base legal que justifique su conservación.</p>

          <h2 id="compartir" style="margin-top:14px">5. Compartir información</h2>
          <p>Compartimos datos con proveedores de pago, logística, hosting y soporte exclusivamente para cumplir con la prestación del servicio. No vendemos tu información personal ni permitimos usos no autorizados.</p>

          <h2 id="seguridad" style="margin-top:14px">6. Seguridad de la información</h2>
          <p>Aplicamos medidas técnicas y organizativas razonables (control de acceso, cifrado en tránsito, políticas internas). Aun así, ningún sistema es infalible; recomendamos buenas prácticas como contraseñas robustas y no compartir credenciales.</p>

          <h2 id="derechos" style="margin-top:14px">7. Tus derechos</h2>
          <p>Puedes solicitar acceso, rectificación, actualización, portabilidad, oposición y eliminación de tus datos. Para ejercer tus derechos, contáctanos desde la sección de <a href="/contacto.php">Contacto</a>. Atenderemos tu solicitud conforme a la normativa aplicable.</p>

          <h2 id="cookies" style="margin-top:14px">8. Cookies y tecnologías similares</h2>
          <p>Usamos cookies para recordar preferencias y mejorar la navegación. Las categorías incluyen:</p>
          <ul>
            <li>Esenciales: necesarias para el funcionamiento del sitio.</li>
            <li>Preferencias: recuerdan idioma y configuraciones.</li>
            <li>Rendimiento: analizan uso y mejoras.</li>
            <li>Marketing: muestran contenido y campañas relevantes (solo con tu consentimiento).</li>
          </ul>
          <p>Puedes gestionar las cookies desde la configuración de tu navegador o mediante los controles que ofrezcamos en el sitio.</p>

          <h2 id="transferencias" style="margin-top:14px">9. Transferencias internacionales</h2>
          <p>Si algún proveedor procesa datos fuera de tu país, adoptamos garantías adecuadas (por ejemplo, cláusulas contractuales tipo) para proteger tu información.</p>

          <h2 id="menores" style="margin-top:14px">10. Menores de edad</h2>
          <p>Nuestros servicios no están dirigidos a menores de 14 años. Si detectamos datos de menores sin autorización, los eliminaremos y tomaremos medidas para proteger su privacidad.</p>

          <h2 id="actualizaciones" style="margin-top:14px">11. Actualizaciones de la política</h2>
          <p>Podemos actualizar esta política para reflejar cambios regulatorios o mejoras del servicio. Publicaremos las actualizaciones en esta página y, si fuera necesario, las comunicaremos por canales adecuados.</p>

          <p class="subtle" style="margin-top:18px">Última actualización: <?php echo date('Y-m-d'); ?>.</p>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer theme-bg">
    <div class="container">
      <div class="footer-content">
        <div class="footer-section">
          <a class="logo" href="/"><span class="mark"></span><span>Revive tu Hogar</span></a>
          <p class="subtle">Transformamos espacios con diseño moderno, mantenimiento profesional y asesoría personalizada. Calidad y cercanía en cada proyecto.</p>
          <div class="footer-social">
            <a href="#" class="social-link" aria-label="Facebook"><span style="font-size:14px;">f</span></a>
            <a href="#" class="social-link" aria-label="Instagram"><span style="font-size:14px;">📷</span></a>
            <a href="#" class="social-link" aria-label="WhatsApp"><span style="font-size:14px;">💬</span></a>
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
            <div class="contact-item"><div class="contact-icon">@</div><span>hola@revivetuhogar.test</span></div>
            <div class="contact-item"><div class="contact-icon">📞</div><span>+57 300 000 0000</span></div>
            <div class="contact-item"><div class="contact-icon">📍</div><span>Armenia, Quindío</span></div>
            <div class="contact-item" style="margin-top:15px;">
              <a href="/contacto.php" style="color:#b8956f; text-decoration:none; font-weight:600; display:flex; align-items:center; gap:8px;">
                <div class="contact-icon">💬</div><span>Enviar consulta</span>
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
        <div class="footer-social" style="display:none;"><small style="color:#999;">Síguenos en redes sociales</small></div>
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

      const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
      document.querySelectorAll('[data-3d]').forEach(el=>{
        const maxTilt = 10;
        const perspective = 900;
        const scaleOn = 1.02;
        el.style.transformStyle = 'preserve-3d';
        el.style.transition = 'transform 120ms ease';
        const rect = () => el.getBoundingClientRect();
        const onMove = (ev) => {
          const r = rect();
          const cx = r.left + r.width/2;
          const cy = r.top + r.height/2;
          const dx = clamp((ev.clientX - cx)/ (r.width/2), -1, 1);
          const dy = clamp((ev.clientY - cy)/ (r.height/2), -1, 1);
          const rx = -dy * maxTilt;
          const ry = dx * maxTilt;
          el.style.transform = `perspective(${perspective}px) rotateX(${rx}deg) rotateY(${ry}deg) scale(${scaleOn})`;
        };
        const reset = () => { el.style.transform = `perspective(${perspective}px) rotateX(0deg) rotateY(0deg) scale(1)`; };
        el.addEventListener('mousemove', onMove);
        el.addEventListener('mouseleave', reset);
        el.addEventListener('mouseenter', (ev)=> onMove(ev));
      });
    })();
  </script>
</body>
</html>