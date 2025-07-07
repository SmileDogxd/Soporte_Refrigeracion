<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$auth = new Auth();
$db = new Database();
$conn = $db->getConnection();

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'tecnico') {
    header('Location: ../index.php');
    exit();
}

$tecnico_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Obtener datos del técnico
$stmt = $conn->prepare("SELECT u.*, t.especialidad, t.experiencia 
                       FROM usuarios u
                       JOIN tecnicos t ON u.id = t.usuario_id
                       WHERE u.id = ?");
$stmt->bind_param("i", $tecnico_id);
$stmt->execute();
$tecnico = $stmt->get_result()->fetch_assoc();

// Obtener estadísticas del técnico
$stats_query = "SELECT 
                COUNT(*) as total_trabajos,
                AVG(c.puntuacion) as calificacion_promedio
                FROM asignaciones a
                JOIN tickets t ON a.ticket_id = t.id
                LEFT JOIN calificaciones c ON t.id = c.reparacion_id
                WHERE a.tecnico_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $tecnico_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $especialidad = trim($_POST['especialidad']);
    $experiencia = trim($_POST['experiencia']);
    $password_actual = trim($_POST['password_actual']);
    $nueva_password = trim($_POST['nueva_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    try {
        // Verificar contraseña actual si se quiere cambiar
        if (!empty($nueva_password)) {
            if (empty($password_actual)) {
                throw new Exception("Debes ingresar tu contraseña actual para cambiarla");
            }
            
            if (!password_verify($password_actual, $tecnico['password'])) {
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
            $hashed_password = $tecnico['password'];
        }
        
        // Actualizar datos del usuario
        $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, direccion = ?, password = ? WHERE id = ?");
        $stmt->bind_param("sssssi", $nombre, $email, $telefono, $direccion, $hashed_password, $tecnico_id);
        $stmt->execute();
        
        // Actualizar datos del técnico
        $stmt = $conn->prepare("UPDATE tecnicos SET especialidad = ?, experiencia = ? WHERE usuario_id = ?");
        $stmt->bind_param("ssi", $especialidad, $experiencia, $tecnico_id);
        $stmt->execute();
        
        // Actualizar datos en sesión
        $_SESSION['user_nombre'] = $nombre;
        $_SESSION['user_email'] = $email;
        
        $success = "Perfil actualizado correctamente";
        
        // Refrescar datos del técnico
        $stmt = $conn->prepare("SELECT u.*, t.especialidad, t.experiencia 
                              FROM usuarios u
                              JOIN tecnicos t ON u.id = t.usuario_id
                              WHERE u.id = ?");
        $stmt->bind_param("i", $tecnico_id);
        $stmt->execute();
        $tecnico = $stmt->get_result()->fetch_assoc();
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
    <style>
        .profile-header {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-card {
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .rating-star {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/navbar_tecnicos.php'; ?>

    <div class="container my-5">
        <div class="profile-header text-center">
            <div class="mb-3">
                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto" 
                     style="width: 120px; height: 120px;">
                    <i class="fas fa-user-cog text-white" style="font-size: 3rem;"></i>
                </div>
            </div>
            <h3><?php echo htmlspecialchars($tecnico['nombre']); ?></h3>
            <p class="text-muted mb-1"><?php echo htmlspecialchars($tecnico['email']); ?></p>
            <p class="text-muted">Técnico desde <?php echo date('d/m/Y', strtotime($tecnico['fecha_registro'])); ?></p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Trabajos Realizados</h5>
                        <h2 class="mb-0"><?php echo $stats['total_trabajos'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card stats-card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Calificación Promedio</h5>
                        <h2 class="mb-0">
                            <?php echo $stats['calificacion_promedio'] ? number_format($stats['calificacion_promedio'], 1) : 'N/A'; ?>
                            <?php if ($stats['calificacion_promedio']): ?>
                                <i class="fas fa-star rating-star"></i>
                            <?php endif; ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-edit"></i> Editar Perfil</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nombre" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" 
                                   value="<?php echo htmlspecialchars($tecnico['nombre']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($tecnico['email']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" 
                                   value="<?php echo htmlspecialchars($tecnico['telefono'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea class="form-control" id="direccion" name="direccion" rows="1"><?php echo htmlspecialchars($tecnico['direccion'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="especialidad" class="form-label">Especialidad *</label>
                            <input type="text" class="form-control" id="especialidad" name="especialidad" 
                                   value="<?php echo htmlspecialchars($tecnico['especialidad']); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="experiencia" class="form-label">Experiencia *</label>
                            <textarea class="form-control" id="experiencia" name="experiencia" rows="1" required><?php echo htmlspecialchars($tecnico['experiencia']); ?></textarea>
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