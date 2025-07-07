<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$auth = new Auth();



$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $rol = $_POST['rol'];
    
    if ($auth->login($email, $password, $rol)) {
        $auth->redirectByRole();
    } else {
        $error = "Credenciales incorrectas o usuario inactivo";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo">
                <h3><i class="fas fa-snowflake"></i> <?php echo APP_NAME; ?></h3>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="rol" id="cliente" value="cliente" checked>
                        <label class="form-check-label" for="cliente">
                            Cliente
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="rol" id="tecnico" value="tecnico">
                        <label class="form-check-label" for="tecnico">
                            Técnico
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="rol" id="admin" value="admin">
                        <label class="form-check-label" for="admin">
                            Administrador
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">Iniciar Sesión</button>
            </form>
            
            <div class="mt-3 text-center">
                <a href="register.php">Registrarse como cliente</a>
            </div>
            <div class="mt-3 text-center">
                <a href="index.html">Regresar</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>