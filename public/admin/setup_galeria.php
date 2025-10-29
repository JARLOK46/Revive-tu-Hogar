<?php
require_once __DIR__.'/../../app/middleware/auth.php';
require_role('admin');
require_once __DIR__.'/../../app/config/db.php';

echo "<h2>Setup de Galería de Proyectos</h2>";

if (!($pdo instanceof PDO)) {
    die("Error: No hay conexión a la base de datos");
}

try {
    // Crear tabla proyectos_galeria
    $sql = "CREATE TABLE IF NOT EXISTS proyectos_galeria (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT,
        imagen_url VARCHAR(255) NOT NULL,
        cliente_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (cliente_id),
        CONSTRAINT fk_galeria_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "<p style='color:green'>✓ Tabla proyectos_galeria creada correctamente</p>";
    
    // Verificar que la tabla existe
    $result = $pdo->query("SHOW TABLES LIKE 'proyectos_galeria'");
    if ($result->rowCount() > 0) {
        echo "<p style='color:green'>✓ Tabla proyectos_galeria confirmada en la base de datos</p>";
        
        // Mostrar estructura de la tabla
        $structure = $pdo->query("DESCRIBE proyectos_galeria")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Estructura de la tabla:</h3>";
        echo "<table border='1' style='border-collapse:collapse; margin:10px 0;'>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($structure as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Contar registros existentes
        $count = $pdo->query("SELECT COUNT(*) FROM proyectos_galeria")->fetchColumn();
        echo "<p>Registros actuales en la tabla: <strong>$count</strong></p>";
        
    } else {
        echo "<p style='color:red'>✗ Error: La tabla no se pudo crear</p>";
    }
    
    // Verificar directorio uploads
    $uploadDir = __DIR__ . '/../uploads/proyectos';
    if (!is_dir($uploadDir)) {
        if (mkdir($uploadDir, 0777, true)) {
            echo "<p style='color:green'>✓ Directorio uploads/proyectos creado</p>";
        } else {
            echo "<p style='color:red'>✗ No se pudo crear el directorio uploads/proyectos</p>";
        }
    } else {
        echo "<p style='color:green'>✓ Directorio uploads/proyectos ya existe</p>";
    }
    
    // Verificar permisos
    if (is_writable($uploadDir)) {
        echo "<p style='color:green'>✓ Directorio tiene permisos de escritura</p>";
    } else {
        echo "<p style='color:red'>✗ Directorio sin permisos de escritura</p>";
    }
    
    // Mostrar información del sistema
    echo "<h3>Información del sistema:</h3>";
    echo "<ul>";
    echo "<li>Límite de subida: " . ini_get('upload_max_filesize') . "</li>";
    echo "<li>Límite POST: " . ini_get('post_max_size') . "</li>";
    echo "<li>Directorio temporal: " . sys_get_temp_dir() . "</li>";
    echo "<li>Extensión finfo: " . (function_exists('finfo_file') ? 'Disponible' : 'No disponible') . "</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='/admin/index.php?entity=galeria'>← Volver a Galería</a></p>";
?>