<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$auth = new Auth();
$db = new Database();
$conn = $db->getConnection();

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'cliente') {
    header('Location: ../index.php');
    exit();
}

$cliente_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Obtener datos del cliente
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();

// Obtener equipos del cliente
$stmt = $conn->prepare("SELECT id, tipo_equipo, marca, modelo FROM equipos WHERE cliente_id = ? ORDER BY tipo_equipo, marca");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$equipos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $password_actual = trim($_POST['password_actual']);
    $nueva_password = trim($_POST['nueva_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    try {
        // Verificar contraseña actual si se quiere cambiar
        if (!empty($nueva_password)) {
            if (empty($password_actual)) {
                throw new Exception("Debes ingresar tu contraseña actual para cambiarla");
            }
            
            if (!password_verify($password_actual, $cliente['password'])) {
                throw new Exception("La contraseña actual es incorrecta");
            }
            
            if ($nueva_password !== $confirm_password) {
                throw new Exception("Las nuevas contraseñas no coinciden");
            }
            
            if (strlen($nueva_password) < 8) {
                throw new Exception("La nueva contraseña debe tener al menos 8 caracteres");
            }
            
            $hashed_password = password_hash($nueva_password, PASSWORD_DEFAULT);
        } else {
            $hashed_password = $cliente['password'];
        }
        
        // Actualizar datos del cliente
        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, direccion = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $nombre, $email, $telefono, $direccion, $hashed_password, $cliente_id);
        $stmt->execute();
        
        // Actualizar datos en sesión
        $_SESSION['user_nombre'] = $nombre;
        $_SESSION['user_email'] = $email;
        
        $success = "Perfil actualizado correctamente";
        
        // Refrescar datos del cliente
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $cliente_id);
        $stmt->execute();
        $cliente = $stmt->get_result()->fetch_assoc();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include_once '../includes/navbar_clientes.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-user-circle"></i> Mi Perfil</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mx-auto" 
                                 style="width: 150px; height: 150px;">
                                <i class="fas fa-user text-white" style="font-size: 4rem;"></i>
                            </div>
                        </div>
                        <h4><?php echo htmlspecialchars($cliente['nombre']); ?></h4>
                        <p class="text-muted mb-1"><?php echo htmlspecialchars($cliente['email']); ?></p>
                        <p class="text-muted">Cliente desde <?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-snowflake"></i> Mis Equipos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($equipos) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($equipos as $equipo): ?>
                                    <a href="historial.php" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($equipo['tipo_equipo']); ?></h6>
                                            <small><i class="fas fa-search"></i></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars("{$equipo['marca']} {$equipo['modelo']}"); ?></p>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No tienes equipos registrados</div>
                            <a href="nuevo_ticket.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Registrar equipo
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Editar Perfil</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="nombre" class="form-label">Nombre Completo *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" 
                                           value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($cliente['email']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="direccion" class="form-label">Dirección</label>
                                    <textarea class="form-control" id="direccion" name="direccion" rows="1"><?php echo htmlspecialchars($cliente['direccion'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <h5 class="border-bottom pb-2">Cambiar Contraseña</h5>
                                    <p class="text-muted">Deja estos campos en blanco si no deseas cambiar la contraseña</p>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="password_actual" class="form-label">Contraseña Actual</label>
                                    <input type="password" class="form-control" id="password_actual" name="password_actual">
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="nueva_password" class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" id="nueva_password" name="nueva_password">
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Guardar Cambios
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validar contraseñas coincidan
        document.getElementById('confirm_password').addEventListener('input', function() {
            const nueva = document.getElementById('nueva_password').value;
            const confirmacion = this.value;
            
            if (nueva !== confirmacion && confirmacion !== '') {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        
        // Validar formulario antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const nueva = document.getElementById('nueva_password').value;
            const confirmacion = document.getElementById('confirm_password').value;
            const actual = document.getElementById('password_actual').value;
            
            // Si se llenó alguno de los campos de contraseña
            if (nueva !== '' || confirmacion !== '' || actual !== '') {
                if (actual === '') {
                    alert('Debes ingresar tu contraseña actual para cambiarla');
                    e.preventDefault();
                    return false;
                }
                
                if (nueva !== confirmacion) {
                    alert('Las nuevas contraseñas no coinciden');
                    e.preventDefault();
                    return false;
                }
                
                if (nueva.length > 0 && nueva.length < 8) {
                    alert('La nueva contraseña debe tener al menos 8 caracteres');
                    e.preventDefault();
                    return false;
                }
            }
            return true;
        });
    </script>
</body>
</html>