<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$auth = new Auth();
$db = new Database();
$conn = $db->getConnection();

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Procesar acciones
switch ($action) {
    case 'add':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);
            $telefono = trim($_POST['telefono']);
            $direccion = trim($_POST['direccion']);
            $especialidad = trim($_POST['especialidad']);
            $experiencia = trim($_POST['experiencia']);
            
            try {
                // Verificar si el email ya existe
                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    throw new Exception("El email ya está registrado");
                }
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $rol = 'tecnico';
                
                // Insertar usuario
                $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, telefono, direccion, rol) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $nombre, $email, $hashed_password, $telefono, $direccion, $rol);
                $stmt->execute();
                $usuario_id = $stmt->insert_id;
                
                // Insertar técnico
                $stmt = $conn->prepare("INSERT INTO tecnicos (usuario_id, especialidad, experiencia) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $usuario_id, $especialidad, $experiencia);
                $stmt->execute();
                
                $success = "Técnico registrado exitosamente";
                $action = 'list'; // Volver a la lista
            } catch (Exception $e) {
                $error = "Error al registrar técnico: " . $e->getMessage();
            }
        }
        break;
        
    case 'edit':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            $telefono = trim($_POST['telefono']);
            $direccion = trim($_POST['direccion']);
            $especialidad = trim($_POST['especialidad']);
            $experiencia = trim($_POST['experiencia']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            try {
                // Actualizar usuario
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, direccion = ?, activo = ? WHERE id = ? AND rol = 'tecnico'");
                $stmt->bind_param("ssssii", $nombre, $email, $telefono, $direccion, $activo, $id);
                $stmt->execute();
                
                // Actualizar técnico
                $stmt = $conn->prepare("UPDATE tecnicos SET especialidad = ?, experiencia = ? WHERE usuario_id = ?");
                $stmt->bind_param("ssi", $especialidad, $experiencia, $id);
                $stmt->execute();
                
                $success = "Técnico actualizado correctamente";
                $action = 'list'; // Volver a la lista
            } catch (Exception $e) {
                $error = "Error al actualizar técnico: " . $e->getMessage();
            }
        } else {
            // Obtener datos del técnico para editar
            $stmt = $conn->prepare("SELECT u.*, t.especialidad, t.experiencia 
                                  FROM usuarios u
                                  JOIN tecnicos t ON u.id = t.usuario_id
                                  WHERE u.id = ? AND u.rol = 'tecnico'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $tecnico = $stmt->get_result()->fetch_assoc();
            
            if (!$tecnico) {
                header('Location: tecnicos.php?error=Técnico no encontrado');
                exit();
            }
        }
        break;
        
    case 'delete':
        // No eliminamos físicamente, solo desactivamos
        $stmt = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE id = ? AND rol = 'tecnico'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        header('Location: tecnicos.php?success=Técnico desactivado correctamente');
        exit();
        break;
        
    case 'activate':
        // Reactivar técnico
        $stmt = $conn->prepare("UPDATE usuarios SET activo = 1 WHERE id = ? AND rol = 'tecnico'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        header('Location: tecnicos.php?success=Técnico activado correctamente');
        exit();
        break;
}

// Obtener lista de técnicos
$query = "SELECT u.*, t.especialidad, t.experiencia, t.calificacion_promedio 
          FROM usuarios u
          JOIN tecnicos t ON u.id = t.usuario_id
          WHERE u.rol = 'tecnico'
          ORDER BY u.nombre";
$tecnicos = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Técnicos - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <?php include_once '../includes/navbar_admin.php'; ?>

    <div class="container my-5">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-user-cog"></i> Gestión de Técnicos</h2>
            <a href="tecnicos.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Nuevo Técnico
            </a>
        </div>
        
        <?php if ($action === 'add' || $action === 'edit'): ?>
            <!-- Formulario para agregar/editar técnico -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-user-edit"></i> <?php echo $action === 'add' ? 'Registrar Nuevo Técnico' : 'Editar Técnico'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="tecnicos.php?action=<?php echo $action; ?><?php echo $action === 'edit' ? '&id='.$id : ''; ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nombre" class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" 
                                       value="<?php echo htmlspecialchars($tecnico['nombre'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($tecnico['email'] ?? ''); ?>" required>
                            </div>
                            
                            <?php if ($action === 'add'): ?>
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Contraseña *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            <?php endif; ?>
                            
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
                                       value="<?php echo htmlspecialchars($tecnico['especialidad'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="experiencia" class="form-label">Experiencia *</label>
                                <textarea class="form-control" id="experiencia" name="experiencia" rows="2" required><?php echo htmlspecialchars($tecnico['experiencia'] ?? ''); ?></textarea>
                            </div>
                            
                            <?php if ($action === 'edit'): ?>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="activo" name="activo" <?php echo ($tecnico['activo'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="activo">
                                            Técnico activo
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar
                                </button>
                                <a href="tecnicos.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Lista de técnicos -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tecnicosTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Especialidad</th>
                                    <th>Calificación</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <tr>
                                        <td><?php echo $tecnico['id']; ?></td>
                                        <td><?php echo htmlspecialchars($tecnico['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($tecnico['email']); ?></td>
                                        <td><?php echo htmlspecialchars($tecnico['especialidad']); ?></td>
                                        <td>
                                            <?php if ($tecnico['calificacion_promedio'] > 0): ?>
                                                <span class="badge bg-primary">
                                                    <?php echo number_format($tecnico['calificacion_promedio'], 1); ?> ★
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Sin calificaciones</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($tecnico['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="tecnicos.php?action=edit&id=<?php echo $tecnico['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($tecnico['activo']): ?>
                                                <a href="tecnicos.php?action=delete&id=<?php echo $tecnico['id']; ?>" class="btn btn-sm btn-danger" title="Desactivar" onclick="return confirm('¿Estás seguro de desactivar este técnico?');">
                                                    <i class="fas fa-user-slash"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="tecnicos.php?action=activate&id=<?php echo $tecnico['id']; ?>" class="btn btn-sm btn-success" title="Activar" onclick="return confirm('¿Estás seguro de activar este técnico?');">
                                                    <i class="fas fa-user-check"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#tecnicosTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                },
                columnDefs: [
                    { orderable: false, targets: [6] } // Deshabilitar ordenación en columna de acciones
                ]
            });
        });
    </script>
</body>
</html>