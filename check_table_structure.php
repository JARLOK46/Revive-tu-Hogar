<?php
require_once 'app/config/db.php';

try {
    // Verificar estructura de la tabla usuarios
    $stmt = $pdo->query("DESCRIBE usuarios");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Estructura de la tabla 'usuarios':\n";
    echo "================================\n";
    foreach ($columns as $column) {
        echo "Campo: " . $column['Field'] . " | Tipo: " . $column['Type'] . " | Null: " . $column['Null'] . "\n";
    }
    
    echo "\n\nTambién verificando si existe la tabla 'consultas':\n";
    echo "================================================\n";
    
    $stmt2 = $pdo->query("DESCRIBE consultas");
    $columns2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns2 as $column) {
        echo "Campo: " . $column['Field'] . " | Tipo: " . $column['Type'] . " | Null: " . $column['Null'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>