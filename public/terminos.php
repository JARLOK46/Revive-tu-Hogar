<?php
require_once __DIR__.'/../app/config/session.php';
require_once __DIR__.'/../app/config/db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Términos y Condiciones - Revive tu Hogar</title>
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
        <h1 class="reveal-left">Términos y Condiciones</h1>
        <p class="lead reveal-right" style="transition-delay:60ms">Lee nuestras condiciones de servicio y uso del sitio.</p>
      </div>
      <div class="card theme-surface float-y reveal" data-3d style="transition-delay:120ms">
        <strong class="badge">Legal</strong>
        <ul>
          <li>Servicios, pedidos y pagos</li>
          <li>Garantías y devoluciones</li>
          <li>Propiedad intelectual y modificaciones</li>
        </ul>
      </div>
    </section>

    <section class="section theme-bg">
      <div class="container">
        <div class="card theme-surface reveal">
          <h2 id="aceptacion">1. Aceptación de los términos</h2>
          <p>Al acceder y utilizar nuestro sitio web, aceptas estos términos y condiciones. Si no estás de acuerdo con alguna parte, te recomendamos no utilizar nuestros servicios. Estos términos pueden complementarse con políticas específicas publicadas en páginas concretas del sitio.</p>

          <h2 id="servicios" style="margin-top:14px">2. Servicios ofrecidos</h2>
          <p>Ofrecemos productos y servicios para el hogar (mantenimiento, renovación, diseño y asesoría). Nos reservamos el derecho de actualizar, modificar o descontinuar productos, funcionalidades y planes sin previo aviso, garantizando en todo caso el respeto a las condiciones vigentes en el momento de contratación.</p>

          <h2 id="pedidos-pagos" style="margin-top:14px">3. Pedidos y pagos</h2>
          <p>Los pedidos se procesan una vez confirmado el pago mediante los métodos habilitados en nuestra plataforma. Los precios pueden variar según promociones y disponibilidad. En caso de error de precio evidente, podremos cancelar el pedido y ofrecer la opción de realizarlo nuevamente con la información correcta.</p>

          <h2 id="envios" style="margin-top:14px">4. Envíos y entregas</h2>
          <p>Las entregas y visitas técnicas se realizan según la dirección proporcionada por el cliente. Los tiempos comunicados son estimados y pueden variar por logística, disponibilidad de materiales, condiciones climáticas u otros factores externos. Mantendremos al cliente informado de cambios relevantes.</p>

          <h2 id="garantias" style="margin-top:14px">5. Garantías y devoluciones</h2>
          <p>Los servicios y productos cuentan con garantías conforme a la ley aplicable y a las políticas internas vigentes. Para devoluciones o reclamaciones, el artículo debe estar en condiciones originales y dentro del plazo establecido. En servicios, se realizarán correcciones razonables si la ejecución no cumple con lo pactado.</p>

          <h2 id="responsabilidades" style="margin-top:14px">6. Responsabilidades del cliente</h2>
          <p>El cliente se compromete a proporcionar información veraz y completa, facilitar el acceso al inmueble cuando sea necesario, y mantener condiciones adecuadas para la ejecución de los trabajos. Asimismo, es responsable de revisar las especificaciones del servicio y comunicar cualquier restricción o particularidad relevante.</p>

          <h2 id="propiedad" style="margin-top:14px">7. Propiedad intelectual</h2>
          <p>El contenido del sitio (textos, diseños, imágenes, logotipos, material gráfico y código) es propiedad de Revive tu Hogar o cuenta con licencias correspondientes. Queda prohibida su reproducción, distribución o explotación sin autorización expresa. Los entregables de proyectos pueden incluir derechos de uso limitados según lo pactado.</p>

          <h2 id="limitacion" style="margin-top:14px">8. Limitación de responsabilidad</h2>
          <p>En la medida permitida por la ley, no seremos responsables por daños indirectos, incidentales o consecuentes derivados del uso del sitio o de los servicios, incluyendo pérdida de beneficios o datos, salvo que medie dolo o culpa grave. Nuestro compromiso es actuar con diligencia profesional y buena fe.</p>

          <h2 id="fuerza-mayor" style="margin-top:14px">9. Fuerza mayor</h2>
          <p>No seremos responsables por incumplimientos causados por eventos de fuerza mayor (desastres naturales, conflictos, fallas masivas de infraestructura, actos gubernamentales, etc.). En tales casos, reprogramaremos actividades y ofreceremos alternativas razonables.</p>

          <h2 id="modificaciones" style="margin-top:14px">10. Modificaciones de los términos</h2>
          <p>Podemos actualizar estos términos para reflejar cambios regulatorios, operativos o de servicio. Las modificaciones se publicarán en esta página y serán aplicables desde su publicación. Si el cambio afecta un contrato vigente, notificaremos al cliente por los medios disponibles.</p>

          <h2 id="contacto" style="margin-top:14px">11. Atención al cliente y contacto</h2>
          <p>Para consultas, reclamaciones o ejercicio de derechos, contáctanos desde la sección de <a href="/contacto.php">Contacto</a> o por los canales indicados en el pie de página.</p>

          <h2 id="ley-jurisdiccion" style="margin-top:14px">12. Ley aplicable y jurisdicción</h2>
          <p>Estos términos se rigen por la legislación colombiana (salvo que se indique otra jurisdicción aplicable). Cualquier controversia será resuelta por los tribunales competentes del domicilio del consumidor o el acordado contractualmente, conforme a las normas de protección al consumidor.</p>

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