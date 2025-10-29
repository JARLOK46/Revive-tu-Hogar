<?php
// app/config/session.php
// Inicia sesión con banderas seguras y regenera el ID periódicamente
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    // Asegurar que la ruta de sesiones sea escribible (evitar Program Files en Windows)
    $defaultPath = ini_get('session.save_path') ?: '';
    $projectSessions = __DIR__ . '/../../storage/sessions';
    if (!is_dir($projectSessions)) { @mkdir($projectSessions, 0777, true); }
    if (is_dir($projectSessions) && is_writable($projectSessions)) {
        session_save_path($projectSessions);
    } else if ($defaultPath === '' || !is_writable($defaultPath) || stripos($defaultPath, 'Program Files') !== false) {
        $fallback = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php_sessions';
        if (!is_dir($fallback)) { @mkdir($fallback, 0777, true); }
        if (is_dir($fallback) && is_writable($fallback)) {
            session_save_path($fallback);
        }
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

if (!function_exists('regenerar_sesion')) {
    function regenerar_sesion(): void {
        if (!isset($_SESSION['__regen_time'])) {
            $_SESSION['__regen_time'] = time();
        }
        if (time() - (int)$_SESSION['__regen_time'] > 300) { // cada 5 min
            session_regenerate_id(true);
            $_SESSION['__regen_time'] = time();
        }
    }
}

regenerar_sesion();