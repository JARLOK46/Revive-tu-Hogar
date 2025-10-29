<?php 
$pageTitle = 'Productos';
require_once __DIR__ . '/../../includes/header.php'; 

// Simulación de categorías (luego se reemplazará con datos de la base de datos)
$categorias = [
    ['id' => 1, 'nombre' => 'Sofás y Sillones', 'icono' => 'couch'],
    ['id' => 2, 'nombre' => 'Camas y Dormitorio', 'icono' => 'bed'],
    ['id' => 3, 'nombre' => 'Comedores', 'icono' => 'utensils'],
    ['id' => 4, 'nombre' => 'Oficina', 'icono' => 'laptop-house'],
    ['id' => 5, 'nombre' => 'Iluminación', 'icono' => 'lightbulb'],
    ['id' => 6, 'nombre' => 'Decoración', 'icono' => 'couch'],
];

// Simulación de productos (luego se reemplazará con datos de la base de datos)
$productos = [
    [
        'id' => 1,
        'nombre' => 'Sofá Moderno de 3 Plazas',
        'imagen' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
        'precio' => 1299.99,
        'categoria_id' => 1,
        'descripcion' => 'Sofá de tela resistente con estructura de madera maciza. Perfecto para salas modernas.',
        'es_destacado' => true,
        'stock' => 15
    ],
    [
        'id' => 2,
        'nombre' => 'Mesa de Comedor Extensible',
        'imagen' => 'https://images.unsplash.com/photo-1565538810643-b5bdb714032a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
        'precio' => 899.99,
        'categoria_id' => 3,
        'descripcion' => 'Mesa de comedor extensible para 6-8 personas, acabado en roble macizo.',
        'es_destacado' => true,
        'stock' => 8
    ],
    [
        'id' => 3,
        'nombre' => 'Cama King Size con Cabecero',
        'imagen' => 'https://images.unsplash.com/photo-1513694203239-dc9e8452a1bd?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
        'precio' => 1499.99,
        'categoria_id' => 2,
        'descripcion' => 'Cama king size con cabecero tapizado en tela de alta calidad y estructura de madera.',
        'es_destacado' => true,
        'stock' => 5
    ],
    [
        'id' => 4,
        'nombre' => 'Silla de Oficina Ergonómica',
        'imagen' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
        'precio' => 349.99,
        'categoria_id' => 4,
        'descripcion' => 'Silla ergonómica con soporte lumbar ajustable y reposacabezas.',
        'es_destacado' => false,
        'stock' => 20
    ],
    [
        'id' => 5,
        'nombre' => 'Lámpara de Pie Moderna',
        'imagen' => 'https://images.unsplash.com/photo-1606170033648-5d55a0ed5045?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
        'precio' => 149.99,
        'categoria_id' => 5,
        'descripcion' => 'Lámpara de pie con diseño minimalista y luz regulable.',
        'es_destacado' => false,
        'stock' => 12
    ],
    [
        'id' => 6,
        'nombre' => 'Juego de Cojines Decorativos',
        'imagen' => 'https://images.unsplash.com/photo-1579656592043-20a4d88a0c58?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
        'precio' => 89.99,
        'categoria_id' => 6,
        'descripcion' => 'Set de 3 cojines decorativos con estampados modernos.',
        'es_destacado' => true,
        'stock' => 30
    ],
    [
        'id' => 7,
        'nombre' => 'Mesa de Centro de Vidrio',
        'imagen' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
        'precio' => 299.99,
        'categoria_id' => 1,
        'descripcion' => 'Mesa de centro con base de metal y vidrio templado.',
        'es_destacado' => false,
        'stock' => 10
    ],
    [
        'id' => 8,
        'nombre' => 'Escritorio de Oficina',
        'imagen' => 'https://images.unsplash.com/photo-1518455022959-fd9b9e2a1e7d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80',
        'precio' => 449.99,
        'categoria_id' => 4,
        'descripcion' => 'Escritorio amplio con cajones y estante superior.',
        'es_destacado' => true,
        'stock' => 7
    ]
];

// Filtrar productos por categoría si se especifica
$categoriaFiltro = $_GET['categoria'] ?? null;
$productosFiltrados = $productos;

if ($categoriaFiltro) {
    $productosFiltrados = array_filter($productos, function($producto) use ($categoriaFiltro) {
        return $producto['categoria_id'] == $categoriaFiltro;
    });
}
?>

<!-- Encabezado de la página -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="fw-bold">Nuestros Productos</h1>
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="ordenarDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            Ordenar por
        </button>
        <ul class="dropdown-menu" aria-labelledby="ordenarDropdown">
            <li><a class="dropdown-item" href="?orden=precio_asc">Precio: Menor a Mayor</a></li>
            <li><a class="dropdown-item" href="?orden=precio_desc">Precio: Mayor a Menor</a></li>
            <li><a class="dropdown-item" href="?orden=nombre_asc">Nombre: A-Z</a></li>
            <li><a class="dropdown-item" href="?orden=nombre_desc">Nombre: Z-A</a></li>
        </ul>
    </div>
</div>

<!-- Filtros y categorías -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Categorías</h5>
            </div>
            <div class="list-group list-group-flush">
                <a href="/Revive%20tu%20hogar/public/productos" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo !$categoriaFiltro ? 'active' : ''; ?>">
                    Todas las categorías
                    <span class="badge bg-primary rounded-pill"><?php echo count($productos); ?></span>
                </a>
                <?php foreach ($categorias as $categoria): 
                    $count = count(array_filter($productos, function($p) use ($categoria) {
                        return $p['categoria_id'] == $categoria['id'];
                    }));
                ?>
                    <a href="?categoria=<?php echo $categoria['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo $categoriaFiltro == $categoria['id'] ? 'active' : ''; ?>">
                        <span><i class="fas fa-<?php echo $categoria['icono']; ?> me-2"></i><?php echo htmlspecialchars($categoria['nombre']); ?></span>
                        <span class="badge bg-primary rounded-pill"><?php echo $count; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Filtro de precios -->
        <div class="card mt-3">
            <div class="card-header bg-white">
                <h5 class="mb-0">Rango de Precios</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="precioMin" class="form-label">Precio mínimo: $<span id="precioMinValue">0</span></label>
                    <input type="range" class="form-range" min="0" max="2000" step="50" id="precioMin" oninput="document.getElementById('precioMinValue').textContent = this.value">
                </div>
                <div class="mb-3">
                    <label for="precioMax" class="form-label">Precio máximo: $<span id="precioMaxValue">2000</span></label>
                    <input type="range" class="form-range" min="0" max="2000" step="50" id="precioMax" value="2000" oninput="document.getElementById('precioMaxValue').textContent = this.value">
                </div>
                <button class="btn btn-primary w-100" onclick="filtrarPorPrecio()">Aplicar Filtro</button>
            </div>
        </div>
    </div>
    
    <!-- Lista de productos -->
    <div class="col-md-9">
        <?php if (count($productosFiltrados) > 0): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($productosFiltrados as $producto): ?>
                    <div class="col">
                        <div class="card h-100 hover-effect">
                            <?php if ($producto['es_destacado']): ?>
                                <span class="position-absolute top-0 start-0 bg-warning text-dark px-2 py-1 m-2 rounded">Destacado</span>
                            <?php endif; ?>
                            <?php if ($producto['stock'] <= 5 && $producto['stock'] > 0): ?>
                                <span class="position-absolute top-0 end-0 bg-danger text-white px-2 py-1 m-2 rounded">¡Últimas unidades!</span>
                            <?php elseif ($producto['stock'] == 0): ?>
                                <span class="position-absolute top-0 end-0 bg-secondary text-white px-2 py-1 m-2 rounded">Agotado</span>
                            <?php endif; ?>
                            
                            <img src="<?php echo htmlspecialchars($producto['imagen']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" style="height: 200px; object-fit: cover;">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($producto['descripcion']); ?></p>
                                <div class="mt-auto">
                                    <p class="h4 text-primary fw-bold mb-3">$<?php echo number_format($producto['precio'], 2); ?></p>
                                    <div class="d-grid gap-2">
                                        <?php if ($producto['stock'] > 0): ?>
                                            <button class="btn btn-primary" onclick="agregarAlCarrito(<?php echo $producto['id']; ?>)">
                                                <i class="fas fa-shopping-cart me-2"></i>Agregar al carrito
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" disabled>Producto agotado</button>
                                        <?php endif; ?>
                                        <a href="/Revive%20tu%20hogar/public/producto/<?php echo $producto['id']; ?>" class="btn btn-outline-secondary">
                                            Ver detalles
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Paginación -->
            <nav aria-label="Navegación de productos" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Anterior</a>
                    </li>
                    <li class="page-item active" aria-current="page">
                        <a class="page-link" href="#">1</a>
                    </li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">Siguiente</a>
                    </li>
                </ul>
            </nav>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-4x text-muted mb-3"></i>
                <h3>No se encontraron productos</h3>
                <p class="text-muted">No hay productos disponibles en esta categoría en este momento.</p>
                <a href="/Revive%20tu%20hogar/public/productos" class="btn btn-primary mt-3">Ver todos los productos</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Script para el filtrado de precios -->
<script>
function filtrarPorPrecio() {
    const precioMin = document.getElementById('precioMin').value;
    const precioMax = document.getElementById('precioMax').value;
    // Aquí iría la lógica para filtrar los productos por precio
    // Por ahora, solo recargamos la página con los parámetros
    window.location.href = `?precio_min=${precioMin}&precio_max=${precioMax}`;
}

function agregarAlCarrito(productoId) {
    // Aquí iría la lógica para agregar el producto al carrito
    // Por ahora, mostramos una notificación
    const toast = document.createElement('div');
    toast.className = 'position-fixed bottom-0 end-0 m-3 p-3 text-white bg-success rounded';
    toast.style.zIndex = '1100';
    toast.innerHTML = 'Producto agregado al carrito <button type="button" class="btn-close btn-close-white ms-2" onclick="this.parentElement.remove()"></button>';
    document.body.appendChild(toast);
    
    // Eliminar la notificación después de 3 segundos
    setTimeout(() => {
        toast.remove();
    }, 3000);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
