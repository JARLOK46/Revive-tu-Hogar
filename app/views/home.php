<?php 
$pageTitle = 'Inicio';
require_once __DIR__ . '/../../includes/header.php'; 
?>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold">Transforma tu hogar con nosotros</h1>
                <p class="lead">Diseñamos espacios que se adaptan a tu estilo de vida y presupuesto. ¡Hacemos realidad tus sueños de hogar ideal!</p>
                <div class="d-grid gap-2 d-sm-flex">
                    <a href="/Revive%20tu%20hogar/public/cotizacion" class="btn btn-light btn-lg px-4 me-sm-3">Solicitar Cotización</a>
                    <a href="/Revive%20tu%20hogar/public/productos" class="btn btn-outline-light btn-lg px-4">Ver Productos</a>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <img src="https://images.unsplash.com/photo-1616486338815-3e98d0241c76?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" 
                     alt="Diseño de interiores moderno" class="img-fluid rounded-3 shadow">
            </div>
        </div>
    </div>
</section>

<!-- Sección de Servicios -->
<section class="mb-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Nuestros Servicios</h2>
            <p class="text-muted">Todo lo que necesitas para renovar tu hogar en un solo lugar</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature h-100">
                    <i class="fas fa-paint-roller"></i>
                    <h4>Diseño de Interiores</h4>
                    <p>Transformamos tus espacios con diseños personalizados que se adaptan a tu estilo de vida y presupuesto.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature h-100">
                    <i class="fas fa-tools"></i>
                    <h4>Remodelaciones</h4>
                    <p>Renovamos cocinas, baños, salas y más con materiales de alta calidad y mano de obra calificada.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature h-100">
                    <i class="fas fa-couch"></i>
                    <h4>Muebles a Medida</h4>
                    <p>Diseñamos y fabricamos muebles exclusivos que maximizan el espacio y la funcionalidad.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Productos Destacados -->
<section class="mb-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Productos Destacados</h2>
            <a href="/Revive%20tu%20hogar/public/productos" class="btn btn-outline-primary">Ver todos los productos</a>
        </div>
        
        <div class="row">
            <?php
            // Simulación de productos (luego se reemplazará con datos reales de la base de datos)
            $productos = [
                [
                    'nombre' => 'Sofá Moderno de 3 Plazas',
                    'imagen' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
                    'precio' => '1,299.99',
                    'descripcion' => 'Sofá de tela resistente con estructura de madera maciza.'
                ],
                [
                    'nombre' => 'Mesa de Comedor',
                    'imagen' => 'https://images.unsplash.com/photo-1565538810643-b5bdb714032a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
                    'precio' => '899.99',
                    'descripcion' => 'Mesa de comedor extensible para 6-8 personas, acabado en roble.'
                ],
                [
                    'nombre' => 'Juego de Dormitorio',
                    'imagen' => 'https://images.unsplash.com/photo-151369420323-9dc119b9d7c7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
                    'precio' => '2,499.99',
                    'descripcion' => 'Juego de dormitorio completo con cama, mesitas y cómoda.'
                ],
                [
                    'nombre' => 'Silla de Oficina Ergonómica',
                    'imagen' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
                    'precio' => '349.99',
                    'descripcion' => 'Silla ergonómica ajustable para máxima comodidad en tu espacio de trabajo.'
                ]
            ];
            
            foreach ($productos as $producto): ?>
                <div class="col-md-3 mb-4">
                    <div class="card h-100 hover-effect">
                        <img src="<?php echo htmlspecialchars($producto['imagen']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($producto['descripcion']); ?></p>
                            <div class="mt-auto">
                                <p class="h5 text-primary fw-bold mb-3">$<?php echo $producto['precio']; ?></p>
                                <button class="btn btn-primary w-100">Agregar al carrito</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Testimonios -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center fw-bold mb-5">Lo que dicen nuestros clientes</h2>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="card-text">"Quedé impresionado con el servicio. Transformaron completamente mi sala de estar en solo dos semanas."</p>
                        <div class="mt-3">
                            <img src="https://randomuser.me/api/portraits/women/32.jpg" class="rounded-circle mb-2" width="60" alt="Cliente satisfecho">
                            <h6 class="mb-0">María González</h6>
                            <small class="text-muted">Cliente desde 2022</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                        </div>
                        <p class="card-text">"Los muebles a medida que me hicieron son exactamente lo que necesitaba. Excelente calidad y atención personalizada."</p>
                        <div class="mt-3">
                            <img src="https://randomuser.me/api/portraits/men/45.jpg" class="rounded-circle mb-2" width="60" alt="Cliente satisfecho">
                            <h6 class="mb-0">Carlos Rodríguez</h6>
                            <small class="text-muted">Cliente desde 2023</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star text-warning"></i>
                            <i class="fas fa-star-half-alt text-warning"></i>
                        </div>
                        <p class="card-text">"Contraté la remodelación de mi cocina y quedé encantada con el resultado. Profesionales en todo momento."</p>
                        <div class="mt-3">
                            <img src="https://randomuser.me/api/portraits/women/68.jpg" class="rounded-circle mb-2" width="60" alt="Cliente satisfecho">
                            <h6 class="mb-0">Ana Martínez</h6>
                            <small class="text-muted">Cliente desde 2021</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-primary text-white">
    <div class="container text-center">
        <h2 class="fw-bold mb-4">¿Listo para transformar tu hogar?</h2>
        <p class="lead mb-4">Solicita una consulta gratuita con uno de nuestros diseñadores de interiores</p>
        <a href="/Revive%20tu%20hogar/public/cotizacion" class="btn btn-light btn-lg px-5">Solicitar Cotización</a>
    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
