<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$auth = new Auth();


$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    
    // Validaciones
    if (empty($nombre) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Todos los campos marcados con * son obligatorios";
    } elseif ($password !== $confirm_password) {
        $error = "Las contraseñas no coinciden";
    } elseif (strlen($password) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres";
    } else {
        try {
            // Registrar el cliente
            if ($auth->registerClient($nombre, $email, $password, $telefono, $direccion)) {
                $success = "Registro exitoso. Ahora puedes iniciar sesión.";
                
                // Limpiar los campos del formulario
                $_POST = array();
            } else {
                $error = "Error al registrar el usuario. Inténtalo de nuevo.";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Cliente - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            margin-bottom: 15px;
            background: #eee;
            border-radius: 3px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            background: transparent;
            transition: width 0.3s, background 0.3s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="logo">
                <h3><i class="fas fa-snowflake"></i> <?php echo APP_NAME; ?></h3>
                <p class="text-muted">Registro de nuevo cliente</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-primary">Ir al Login</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Contraseña *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="password-strength-bar"></div>
                            </div>
                            <small class="text-muted">Mínimo 8 caracteres</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" 
                                   value="<?php echo htmlspecialchars($_POST['telefono'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="1"><?php echo htmlspecialchars($_POST['direccion'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="terminos" required>
                        <label class="form-check-label" for="terminos">Acepto los términos y condiciones *</label>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Registrarse
                        </button>
                    </div>
                    
                    <div class="mt-3 text-center">
                        <p>¿Ya tienes una cuenta? <a href="index.php">Inicia sesión aquí</a></p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Validación de fortaleza de contraseña
            $('#password').on('input', function() {
                const password = $(this).val();
                const strengthBar = $('#password-strength-bar');
                let strength = 0;
                
                if (password.length >= 8) strength += 1;
                if (password.match(/[a-z]/)) strength += 1;
                if (password.match(/[A-Z]/)) strength += 1;
                if (password.match(/[0-9]/)) strength += 1;
                if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
                
                // Actualizar la barra de fortaleza
                switch(strength) {
                    case 0:
                        strengthBar.css({'width': '0%', 'background': 'transparent'});
                        break;
                    case 1:
                        strengthBar.css({'width': '20%', 'background': '#dc3545'});
                        break;
                    case 2:
                        strengthBar.css({'width': '40%', 'background': '#fd7e14'});
                        break;
                    case 3:
                        strengthBar.css({'width': '60%', 'background': '#ffc107'});
                        break;
                    case 4:
                        strengthBar.css({'width': '80%', 'background': '#28a745'});
                        break;
                    case 5:
                        strengthBar.css({'width': '100%', 'background': '#20c997'});
                        break;
                }
            });
            
            // Validación de coincidencia de contraseñas
            $('#confirm_password').on('input', function() {
                const password = $('#password').val();
                const confirm = $(this).val();
                
                if (confirm !== '' && password !== confirm) {
                    $(this).addClass('is-invalid');
                } else {
                    $(this).removeClass('is-invalid');
                }
            });
        });
    </script>
</body>
</html>