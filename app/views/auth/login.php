<?php 
$pageTitle = 'Iniciar Sesión';
require_once __DIR__ . '/../../../includes/header.php'; 

// Procesar el formulario de inicio de sesión
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $recordar = isset($_POST['recordar']);
    
    // Validaciones básicas
    if (empty($email) || empty($password)) {
        $error = 'Por favor, completa todos los campos';
    } else {
        // Aquí iría la lógica de autenticación con la base de datos
        // Por ahora, simulamos un inicio de sesión exitoso
        if ($email === 'admin@revivetuhogar.com' && $password === 'admin123') {
            // Iniciar sesión
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = 'Administrador';
            $_SESSION['email'] = $email;
            $_SESSION['is_admin'] = true;
            
            // Configurar cookie de "recordar" si está marcado
            if ($recordar) {
                $token = bin2hex(random_bytes(32));
                $expiracion = time() + (30 * 24 * 60 * 60); // 30 días
                setcookie('remember_token', $token, $expiracion, '/');
                // Aquí deberías guardar el token en la base de datos asociado al usuario
            }
            
            // Redirigir al dashboard o a la página anterior
            $redirect = $_SESSION['redirect_after_login'] ?? '/';
            unset($_SESSION['redirect_after_login']);
            header("Location: $redirect");
            exit();
        } else {
            $error = 'Correo electrónico o contraseña incorrectos';
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h2 class="h4 text-center mb-0">Iniciar Sesión</h2>
                </div>
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="recordar" name="recordar">
                                <label class="form-check-label" for="recordar">Recordar sesión</label>
                            </div>
                            <a href="/Revive%20tu%20hogar/public/recuperar-contrasena" class="text-decoration-none">¿Olvidaste tu contraseña?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2 mb-3">Iniciar Sesión</button>
                        
                        <div class="text-center mt-3">
                            <p class="mb-0">¿No tienes una cuenta? <a href="/Revive%20tu%20hogar/public/registro" class="text-primary">Regístrate</a></p>
                        </div>
                        
                        <div class="position-relative my-4">
                            <hr>
                            <div class="position-absolute top-50 start-50 translate-middle bg-white px-3">
                                <span class="text-muted">O inicia sesión con</span>
                            </div>
                        </div>
                        
                        <div class="row g-2">
                            <div class="col">
                                <a href="#" class="btn btn-outline-primary w-100">
                                    <i class="fab fa-google me-2"></i> Google
                                </a>
                            </div>
                            <div class="col">
                                <a href="#" class="btn btn-outline-primary w-100">
                                    <i class="fab fa-facebook-f me-2"></i> Facebook
                                </a>
                            </div>
                            <div class="col">
                                <a href="#" class="btn btn-outline-primary w-100">
                                    <i class="fab fa-apple me-2"></i> Apple
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p class="text-muted">¿Eres un profesional? <a href="/Revive%20tu%20hogar/public/profesionales/login" class="text-primary">Accede al área de profesionales</a></p>
            </div>
        </div>
    </div>
</div>

<script>
// Mostrar/ocultar contraseña
document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this.querySelector('i');
    
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});

// Validación del formulario
document.querySelector('form').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    if (!email || !password) {
        e.preventDefault();
        alert('Por favor, completa todos los campos');
        return false;
    }
    
    // Validación de formato de correo electrónico simple
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Por favor, ingresa un correo electrónico válido');
        return false;
    }
    
    return true;
});
</script>

<?php require_once __DIR__ . '/../../../includes/footer.php'; ?>
