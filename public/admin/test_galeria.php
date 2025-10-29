<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('admin');
require_once __DIR__.'/../../app/config/db.php';

$mensaje = '';
$debug = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debug[] = "POST recibido";
    $debug[] = "Datos POST: " . json_encode($_POST);
    $debug[] = "Datos FILES: " . json_encode($_FILES);
    
    try {
        // Verificar conexión a BD
        if (!($pdo instanceof PDO)) {
            throw new Exception("No hay conexión a la base de datos");
        }
        $debug[] = "Conexión a BD: OK";
        
        // Verificar tabla
        $result = $pdo->query("SHOW TABLES LIKE 'proyectos_galeria'");
        if ($result->rowCount() == 0) {
            throw new Exception("La tabla proyectos_galeria no existe");
        }
        $debug[] = "Tabla proyectos_galeria: Existe";
        
        // Obtener datos del formulario
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        
        if (empty($titulo)) {
            throw new Exception("Título es requerido");
        }
        
        $debug[] = "Título: '$titulo'";
        $debug[] = "Descripción: '$descripcion'";
        
        // Insertar en BD (sin imagen por ahora)
        $sql = "INSERT INTO proyectos_galeria (titulo, descripcion, imagen_url, cliente_id) VALUES (?, ?, ?, NULL)";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$titulo, $descripcion, '/uploads/proyectos/test.jpg']);
        
        if ($result) {
            $id = $pdo->lastInsertId();
            $debug[] = "Inserción exitosa con ID: $id";
            $mensaje = "✓ Registro guardado correctamente con ID: $id";
        } else {
            throw new Exception("Error al ejecutar la consulta");
        }
        
    } catch (Exception $e) {
        $debug[] = "ERROR: " . $e->getMessage();
        $mensaje = "✗ Error: " . $e->getMessage();
    }
}

// Mostrar registros existentes
$registros = [];
try {
    if ($pdo instanceof PDO) {
        $stmt = $pdo->query("SELECT * FROM proyectos_galeria ORDER BY created_at DESC LIMIT 10");
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $debug[] = "Error al cargar registros: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Galería</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        form { background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Test de Galería - Inserción en BD</h1>
    
    <?php if ($mensaje): ?>
        <div class="debug <?php echo strpos($mensaje, '✓') === 0 ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($mensaje); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($debug)): ?>
        <div class="debug">
            <strong>Debug Info:</strong><br>
            <?php foreach($debug as $info): ?>
                • <?php echo htmlspecialchars($info); ?><br>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <h3>Formulario de Prueba</h3>
        <label>Título:</label>
        <input type="text" name="titulo" required>
        
        <label>Descripción:</label>
        <textarea name="descripcion" rows="3"></textarea>
        
        <button type="submit">Guardar en BD</button>
    </form>
    
    <h3>Registros en la tabla (últimos 10):</h3>
    <?php if (!empty($registros)): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Descripción</th>
                <th>Imagen URL</th>
                <th>Cliente ID</th>
                <th>Fecha</th>
            </tr>
            <?php foreach($registros as $reg): ?>
                <tr>
                    <td><?php echo $reg['id']; ?></td>
                    <td><?php echo htmlspecialchars($reg['titulo']); ?></td>
                    <td><?php echo htmlspecialchars($reg['descripcion']); ?></td>
                    <td><?php echo htmlspecialchars($reg['imagen_url']); ?></td>
                    <td><?php echo $reg['cliente_id'] ?? 'NULL'; ?></td>
                    <td><?php echo $reg['created_at']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No hay registros en la tabla.</p>
    <?php endif; ?>
    
    <p><a href="/admin/index.php?entity=galeria">← Volver a Galería</a></p>
</body>
</html>