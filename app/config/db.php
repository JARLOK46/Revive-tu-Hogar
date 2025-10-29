<?php
// app/config/db.php
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_CHARSET = 'utf8mb4';

// Permite DB por variable de entorno y dos alternativas comunes según tu dump
$envDb = getenv('DB_NAME') ?: null;
$dbCandidates = array_values(array_unique(array_filter([$envDb, 'revive_tu_hogar', 'revivetuhogar'])));

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = null; $lastEx = null;
foreach ($dbCandidates as $name) {
    try {
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$name;charset=$DB_CHARSET", $DB_USER, $DB_PASS, $options);
        // Define nombre de BD activo por si se requiere
        if (!defined('APP_DB_NAME')) define('APP_DB_NAME', $name);
        break;
    } catch (PDOException $e) {
        $lastEx = $e;
        continue;
    }
}

if (!$pdo) {
    http_response_code(500);
    echo 'Error de conexión a la base de datos. Verifique nombre de BD y credenciales.';
    if (php_sapi_name() === 'cli' && $lastEx) {
        fwrite(STDERR, $lastEx->getMessage() . PHP_EOL);
    }
    exit;
}