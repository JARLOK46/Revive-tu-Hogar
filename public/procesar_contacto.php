<?php
require_once '../app/config/session.php';
require_once '../app/config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php?redirect=' . urlencode('/contacto.php'));
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contacto.php');
    exit;
}

// Obtener y validar datos del formulario
$asunto = trim($_POST['asunto'] ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');
$prioridad = trim($_POST['prioridad'] ?? 'media');
$usuario_id = $_SESSION['user_id'];

// Validaciones
$errores = [];

if (empty($asunto)) {
    $errores[] = 'El asunto es obligatorio.';
}

if (empty($mensaje)) {
    $errores[] = 'El mensaje es obligatorio.';
} elseif (strlen($mensaje) < 10) {
    $errores[] = 'El mensaje debe tener al menos 10 caracteres.';
} elseif (strlen($mensaje) > 2000) {
    $errores[] = 'El mensaje no puede exceder 2000 caracteres.';
}

if (!in_array($prioridad, ['baja', 'media', 'alta'])) {
    $prioridad = 'media';
}

// Si hay errores, redirigir con mensaje de error
if (!empty($errores)) {
    $_SESSION['error_message'] = implode(' ', $errores);
    header('Location: /contacto.php');
    exit;
}

// Verificar que PDO esté disponible
if (!$pdo) {
    error_log("PDO no está disponible en procesar_contacto.php");
    $_SESSION['error_message'] = 'Error de conexión a la base de datos. Por favor, inténtalo de nuevo.';
    header('Location: /contacto.php');
    exit;
}

try {
    // Insertar la consulta en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO consultas (usuario_id, asunto, mensaje, estado, created_at, updated_at) 
        VALUES (?, ?, ?, 'pendiente', NOW(), NOW())
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparando consulta de inserción: " . implode(", ", $pdo->errorInfo()));
    }
    
    $resultado = $stmt->execute([$usuario_id, $asunto, $mensaje]);
    
    if ($resultado) {
        $consulta_id = $pdo->lastInsertId();
        
        // Obtener información del usuario para el mensaje de confirmación
        $stmt_usuario = $pdo->prepare("SELECT nombre_usuario, correo_electronico FROM usuarios WHERE id = ?");
        if (!$stmt_usuario) {
            throw new Exception("Error preparando consulta de usuario: " . implode(", ", $pdo->errorInfo()));
        }
        $stmt_usuario->execute([$usuario_id]);
        $usuario = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
        
        // Verificar que se obtuvo información del usuario
        if (!$usuario || !is_array($usuario)) {
            error_log("No se pudo obtener información del usuario ID: " . $usuario_id);
            // Continuar sin la información del usuario, solo con el mensaje de éxito
        }
        
        // Mensaje de éxito
        $_SESSION['success_message'] = "Tu consulta ha sido enviada exitosamente. Te responderemos lo antes posible.";
        
        // Opcional: Enviar notificación por email al administrador
        // (Aquí podrías implementar el envío de email si tienes configurado un sistema de correo)
        
        // Redirigir a la página de consultas del usuario
        header('Location: /mis_consultas.php?nueva=' . $consulta_id);
        exit;
        
    } else {
        throw new Exception('Error al guardar la consulta en la base de datos.');
    }
    
} catch (Exception $e) {
    // Log del error (en un entorno de producción, usar un sistema de logs apropiado)
    error_log("Error al procesar consulta: " . $e->getMessage());
    
    $_SESSION['error_message'] = 'Ocurrió un error al enviar tu consulta. Por favor, inténtalo de nuevo.';
    header('Location: /contacto.php');
    exit;
}
?>