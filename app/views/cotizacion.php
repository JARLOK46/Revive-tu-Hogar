<?php 
$pageTitle = 'Solicitar Cotización';
require_once __DIR__ . '/../../includes/header.php'; 

// Procesar el envío del formulario
$enviado = false;
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos requeridos
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $tipo_servicio = $_POST['tipo_servicio'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');
    $presupuesto = trim($_POST['presupuesto'] ?? '');
    $fecha_deseada = $_POST['fecha_deseada'] ?? '';
    $metodo_contacto = $_POST['metodo_contacto'] ?? [];
    
    // Validaciones
    if (empty($nombre)) {
        $errores[] = "El nombre es obligatorio";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Por favor, ingresa un correo electrónico válido";
    }
    
    if (empty($telefono)) {
        $errores[] = "El teléfono es obligatorio";
    }
    
    if (empty($tipo_servicio)) {
        $errores[] = "Debes seleccionar un tipo de servicio";
    }
    
    if (empty($descripcion)) {
        $errores[] = "Por favor, describe tu proyecto";
    }
    
    // Si no hay errores, procesar el formulario
    if (empty($errores)) {
        // Aquí iría la lógica para guardar la cotización en la base de datos
        // Por ahora, simplemente marcamos como enviado
        $enviado = true;
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h2 class="h4 mb-0">Solicitar Cotización</h2>
                <p class="mb-0">Completa el formulario y nos pondremos en contacto contigo a la brevedad</p>
            </div>
            <div class="card-body">
                <?php if ($enviado): ?>
                    <div class="alert alert-success">
                        <h4 class="alert-heading">¡Gracias por tu solicitud!</h4>
                        <p>Hemos recibido tu solicitud de cotización. Uno de nuestros asesores se pondrá en contacto contigo en las próximas 24 horas para discutir los detalles de tu proyecto.</p>
                        <hr>
                        <p class="mb-0">¿Neitas ayuda inmediata? Llámanos al <strong>+1 234 567 890</strong>.</p>
                    </div>
                    <div class="text-center mt-4">
                        <a href="/Revive%20tu%20hogar/public/" class="btn btn-primary">Volver al inicio</a>
                    </div>
                <?php else: ?>
                    <?php if (!empty($errores)): ?>
                        <div class="alert alert-danger">
                            <h5 class="alert-heading">Por favor, corrige los siguientes errores:</h5>
                            <ul class="mb-0">
                                <?php foreach ($errores as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <!-- Información personal -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-3">Información Personal</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="nombre" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required 
                                           value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="telefono" class="form-label">Teléfono <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" required
                                           value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="direccion" class="form-label">Dirección</label>
                                    <input type="text" class="form-control" id="direccion" name="direccion"
                                           value="<?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detalles del proyecto -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-3">Detalles del Proyecto</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="tipo_servicio" class="form-label">Tipo de servicio <span class="text-danger">*</span></label>
                                    <select class="form-select" id="tipo_servicio" name="tipo_servicio" required>
                                        <option value="" disabled selected>Selecciona una opción</option>
                                        <option value="diseno_interiores" <?php echo ($_POST['tipo_servicio'] ?? '') === 'diseno_interiores' ? 'selected' : ''; ?>>Diseño de Interiores</option>
                                        <option value="remodelacion_cocina" <?php echo ($_POST['tipo_servicio'] ?? '') === 'remodelacion_cocina' ? 'selected' : ''; ?>>Remodelación de Cocina</option>
                                        <option value="remodelacion_bano" <?php echo ($_POST['tipo_servicio'] ?? '') === 'remodelacion_bano' ? 'selected' : ''; ?>>Remodelación de Baño</option>
                                        <option value="muebles_medida" <?php echo ($_POST['tipo_servicio'] ?? '') === 'muebles_medida' ? 'selected' : ''; ?>>Muebles a Medida</option>
                                        <option value="pintura" <?php echo ($_POST['tipo_servicio'] ?? '') === 'pintura' ? 'selected' : ''; ?>>Pintura</option>
                                        <option value="otro" <?php echo ($_POST['tipo_servicio'] ?? '') === 'otro' ? 'selected' : ''; ?>>Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="presupuesto" class="form-label">Presupuesto aproximado</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" id="presupuesto" name="presupuesto"
                                               placeholder="Ej: 5000" value="<?php echo htmlspecialchars($_POST['presupuesto'] ?? ''); ?>">
                                    </div>
                                    <div class="form-text">No es obligatorio, pero nos ayuda a darte mejores opciones.</div>
                                </div>
                                <div class="col-12">
                                    <label for="descripcion" class="form-label">Describe tu proyecto <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="descripcion" name="descripcion" rows="5" required><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                                    <div class="form-text">Sé lo más detallado posible. Incluye medidas, estilos que te gustan, colores preferidos, etc.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="fecha_deseada" class="form-label">Fecha deseada para comenzar</label>
                                    <input type="date" class="form-control" id="fecha_deseada" name="fecha_deseada"
                                           min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['fecha_deseada'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">¿Cómo prefieres que te contactemos? <span class="text-danger">*</span></label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="contacto_email" name="metodo_contacto[]" value="email" 
                                               <?php echo in_array('email', $_POST['metodo_contacto'] ?? []) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="contacto_email">Correo electrónico</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="contacto_whatsapp" name="metodo_contacto[]" value="whatsapp"
                                               <?php echo in_array('whatsapp', $_POST['metodo_contacto'] ?? []) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="contacto_whatsapp">WhatsApp</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="contacto_llamada" name="metodo_contacto[]" value="llamada"
                                               <?php echo in_array('llamada', $_POST['metodo_contacto'] ?? []) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="contacto_llamada">Llamada telefónica</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Adjuntar archivos -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-3">Adjuntar Archivos (Opcional)</h5>
                            <p>Puedes adjuntar fotos, planos o documentos que ayuden a entender mejor tu proyecto.</p>
                            <div class="mb-3">
                                <input class="form-control" type="file" id="archivos" name="archivos[]" multiple>
                                <div class="form-text">Formatos aceptados: JPG, PNG, PDF (Máx. 10MB por archivo)</div>
                            </div>
                        </div>
                        
                        <!-- Términos y condiciones -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terminos" name="terminos" required>
                                <label class="form-check-label" for="terminos">
                                    Acepto la <a href="/Revive%20tu%20hogar/public/privacidad" target="_blank">Política de Privacidad</a> y los 
                                    <a href="/Revive%20tu%20hogar/public/terminos" target="_blank">Términos de Servicio</a> <span class="text-danger">*</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter" checked>
                                <label class="form-check-label" for="newsletter">
                                    Me gustaría recibir ofertas y consejos de decoración por correo electrónico
                                </label>
                            </div>
                        </div>
                        
                        <!-- Botón de envío -->
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-outline-secondary me-md-2">Limpiar formulario</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Enviar solicitud
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Información adicional -->
        <div class="row mt-4">
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-phone-alt fa-2x text-primary mb-3"></i>
                        <h5>Llamada sin compromiso</h5>
                        <p class="text-muted">¿Prefieres hablar directamente con un asesor?</p>
                        <a href="tel:+1234567890" class="btn btn-outline-primary">Llamar ahora</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-comments fa-2x text-primary mb-3"></i>
                        <h5>Chat en vivo</h5>
                        <p class="text-muted">Chatea con nosotros en tiempo real</p>
                        <button class="btn btn-outline-primary" onclick="iniciarChat()">Iniciar chat</button>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-map-marker-alt fa-2x text-primary mb-3"></i>
                        <h5>Visítanos</h5>
                        <p class="text-muted">Agenda una cita en nuestra tienda</p>
                        <a href="#ubicacion" class="btn btn-outline-primary">Ver ubicación</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Preguntas frecuentes -->
<section class="my-5">
    <div class="container">
        <h2 class="text-center mb-4">Preguntas Frecuentes</h2>
        <div class="accordion" id="preguntasFrecuentes">
            <div class="accordion-item">
                <h3 class="accordion-header" id="pregunta1">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#respuesta1">
                        ¿Cuánto tiempo tarda en llegar una cotización?
                    </button>
                </h3>
                <div id="respuesta1" class="accordion-collapse collapse show" data-bs-parent="#preguntasFrecuentes">
                    <div class="accordion-body">
                        Normalmente enviamos cotizaciones dentro de las 24-48 horas hábiles posteriores a la solicitud. Para proyectos más complejos, podríamos necesitar un poco más de tiempo para ofrecerte la mejor solución posible.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header" id="pregunta2">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#respuesta2">
                        ¿Qué información debo incluir en mi solicitud de cotización?
                    </button>
                </h3>
                <div id="respuesta2" class="accordion-collapse collapse" data-bs-parent="#preguntasFrecuentes">
                    <div class="accordion-body">
                        Para ofrecerte la cotización más precisa, es útil que incluyas: medidas del espacio, estilo que te gusta (moderno, clásico, rústico, etc.), materiales que prefieres, fotos del espacio actual, y cualquier otra preferencia o requisito especial que tengas.
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h3 class="accordion-header" id="pregunta3">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#respuesta3">
                        ¿Ofrecen garantía en sus trabajos?
                    </button>
                </h3>
                <div id="respuesta3" class="accordion-collapse collapse" data-bs-parent="#preguntasFrecuentes">
                    <div class="accordion-body">
                        Sí, todos nuestros trabajos cuentan con garantía. La duración y cobertura de la garantía varían según el tipo de servicio y materiales utilizados. Los detalles específicos de la garantía se incluirán en tu cotización y contrato.
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
function iniciarChat() {
    // Aquí iría la lógica para iniciar el chat
    alert('Nuestro servicio de chat está disponible de lunes a viernes de 9:00 a 18:00. Por favor, inténtalo durante nuestro horario laboral.');
}

// Validación de archivos
const inputArchivos = document.getElementById('archivos');
if (inputArchivos) {
    inputArchivos.addEventListener('change', function() {
        const archivos = this.files;
        const tamanoMaximo = 10 * 1024 * 1024; // 10MB
        const formatosPermitidos = ['image/jpeg', 'image/png', 'application/pdf'];
        
        for (let i = 0; i < archivos.length; i++) {
            if (archivos[i].size > tamanoMaximo) {
                alert(`El archivo ${archivos[i].name} excede el tamaño máximo de 10MB`);
                this.value = ''; // Limpiar el input
                return;
            }
            
            if (!formatosPermitidos.includes(archivos[i].type)) {
                alert(`El formato del archivo ${archivos[i].name} no está permitido. Solo se aceptan JPG, PNG y PDF.`);
                this.value = ''; // Limpiar el input
                return;
            }
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
