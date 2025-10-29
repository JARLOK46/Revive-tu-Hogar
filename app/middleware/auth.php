<?php
// app/middleware/auth.php
require_once __DIR__.'/../config/session.php';

function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

function require_role(string $role) {
    require_auth();
    if (($_SESSION['rol'] ?? '') !== $role) {
        http_response_code(403);
        exit('Acceso denegado');
    }
}