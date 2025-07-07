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
    case 'edit':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre = trim($_POST['nombre']);
            $email = trim($_POST['email']);
            $telefono = trim($_POST['telefono']);
            $direccion = trim($_POST['direccion']);
            $activo = isset($_POST['activo']) ? 1 : 0;
            
            try {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, direccion = ?, activo = ? WHERE id = ? AND rol = 'cliente'");
                $stmt->bind_param("ssssii", $nombre, $email, $telefono, $direccion, $activo, $id);
                $stmt->execute();
                
                $success = "Cliente actualizado correctamente";
                $action = 'list'; // Volver a la lista después de editar
            } catch (Exception $e) {
                $error = "Error al actualizar el cliente: " . $e->getMessage();
            }
        } else {
            // Obtener datos del cliente para editar
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ? AND rol = 'cliente'");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $cliente = $stmt->get_result()->fetch_assoc();
            
            if (!$cliente) {
                header('Location: clientes.php?error=Cliente no encontrado');
                exit();
            }
        }
        break;
        
    case 'delete':
        // No eliminamos físicamente, solo desactivamos
        $stmt = $conn->prepare("UPDATE usuarios SET activo = 0 WHERE id = ? AND rol = 'cliente'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        header('Location: clientes.php?success=Cliente desactivado correctamente');
        exit();
        break;
        
    case 'activate':
        // Reactivar cliente
        $stmt = $conn->prepare("UPDATE usuarios SET activo = 1 WHERE id = ? AND rol = 'cliente'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        header('Location: clientes.php?success=Cliente activado correctamente');
        exit();
        break;
}

// Obtener lista de clientes
$query = "SELECT * FROM usuarios WHERE rol = 'cliente' ORDER BY nombre";
$clientes = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes - <?php echo APP_NAME; ?></title>
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
            <h2><i class="fas fa-users"></i> Gestión de Clientes</h2>
        </div>
        
        <?php if ($action === 'edit'): ?>
            <!-- Formulario de edición -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-edit"></i> Editar Cliente</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="clientes.php?action=edit&id=<?php echo $id; ?>">
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
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="activo" name="activo" <?php echo $cliente['activo'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="activo">
                                        Cliente activo
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                                <a href="clientes.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Lista de clientes -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="clientesTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Teléfono</th>
                                    <th>Registro</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td><?php echo $cliente['id']; ?></td>
                                        <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                                        <td><?php echo htmlspecialchars($cliente['telefono'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($cliente['fecha_registro'])); ?></td>
                                        <td>
                                            <?php if ($cliente['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="clientes.php?action=edit&id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($cliente['activo']): ?>
                                                <a href="clientes.php?action=delete&id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-danger" title="Desactivar" onclick="return confirm('¿Estás seguro de desactivar este cliente?');">
                                                    <i class="fas fa-user-slash"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="clientes.php?action=activate&id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-success" title="Activar" onclick="return confirm('¿Estás seguro de activar este cliente?');">
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
            $('#clientesTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                }
            });
        });
    </script>
</body>
</html>