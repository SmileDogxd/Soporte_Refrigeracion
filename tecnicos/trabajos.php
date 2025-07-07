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
$ticket_id = $_GET['ticket_id'] ?? 0;

// Obtener todos los trabajos del técnico
$query = "SELECT t.id, t.titulo, t.estado, t.fecha_creacion, 
          e.tipo_equipo, e.marca, e.modelo,
          u.nombre as cliente_nombre, u.telefono as cliente_telefono
          FROM tickets t
          JOIN equipos e ON t.equipo_id = e.id
          JOIN usuarios u ON t.cliente_id = u.id
          JOIN asignaciones a ON t.id = a.ticket_id
          WHERE a.tecnico_id = ?
          ORDER BY 
          CASE WHEN t.estado = 'en_proceso' THEN 1
               WHEN t.estado = 'asignado' THEN 2
               WHEN t.estado = 'pendiente' THEN 3
               ELSE 4 END,
          t.fecha_creacion DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $tecnico_id);
$stmt->execute();
$trabajos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener tickets disponibles para asignarse
$query_disponibles = "SELECT t.id, t.titulo, t.fecha_creacion, 
                     e.tipo_equipo, e.marca, e.modelo,
                     u.nombre as cliente_nombre
                     FROM tickets t
                     JOIN equipos e ON t.equipo_id = e.id
                     JOIN usuarios u ON t.cliente_id = u.id
                     WHERE t.estado = 'pendiente'
                     AND NOT EXISTS (
                         SELECT 1 FROM asignaciones a 
                         WHERE a.ticket_id = t.id
                     )
                     ORDER BY t.fecha_creacion DESC";
$tickets_disponibles = $conn->query($query_disponibles)->fetch_all(MYSQLI_ASSOC);

// Si se solicita un ticket específico, obtener sus detalles
$ticket_detalle = null;
$asignacion = null;

if ($ticket_id > 0) {
    // Verificar que el técnico está asignado a este ticket
    $query_detalle = "SELECT t.*, e.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono,
                     a.id as asignacion_id, a.fecha_asignacion, a.fecha_aceptacion
                     FROM tickets t
                     JOIN equipos e ON t.equipo_id = e.id
                     JOIN usuarios u ON t.cliente_id = u.id
                     JOIN asignaciones a ON t.id = a.ticket_id
                     WHERE t.id = ? AND a.tecnico_id = ?";
    $stmt = $conn->prepare($query_detalle);
    $stmt->bind_param("ii", $ticket_id, $tecnico_id);
    $stmt->execute();
    $ticket_detalle = $stmt->get_result()->fetch_assoc();
    
    if ($ticket_detalle) {
        $asignacion = [
            'id' => $ticket_detalle['asignacion_id'],
            'fecha_asignacion' => $ticket_detalle['fecha_asignacion'],
            'fecha_aceptacion' => $ticket_detalle['fecha_aceptacion']
        ];
    }
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'aceptar_trabajo' && $ticket_detalle) {
            // Aceptar el trabajo (cambiar estado a en_proceso)
            $stmt = $conn->prepare("UPDATE tickets SET estado = 'en_proceso' WHERE id = ?");
            $stmt->bind_param("i", $ticket_id);
            $stmt->execute();
            
            // Registrar fecha de aceptación
            $stmt = $conn->prepare("UPDATE asignaciones SET fecha_aceptacion = NOW() WHERE ticket_id = ? AND tecnico_id = ?");
            $stmt->bind_param("ii", $ticket_id, $tecnico_id);
            $stmt->execute();
            
            header("Location: trabajos.php?ticket_id=$ticket_id&success=Trabajo aceptado correctamente");
            exit();
        } elseif ($action === 'tomar_trabajo') {
            $ticket_id_tomar = $_POST['ticket_id'] ?? 0;
            
            // Asignar el ticket al técnico
            $stmt = $conn->prepare("INSERT INTO asignaciones (ticket_id, tecnico_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $ticket_id_tomar, $tecnico_id);
            $stmt->execute();
            
            // Cambiar estado del ticket a asignado
            $stmt = $conn->prepare("UPDATE tickets SET estado = 'asignado' WHERE id = ?");
            $stmt->bind_param("i", $ticket_id_tomar);
            $stmt->execute();
            
            header("Location: trabajos.php?ticket_id=$ticket_id_tomar&success=Trabajo asignado correctamente");
            exit();
        }
    } catch (Exception $e) {
        $error = "Error al procesar la acción: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Trabajos - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            font-weight: bold;
        }
        .status-pendiente { color: #6c757d; }
        .status-asignado { color: #0d6efd; }
        .status-en_proceso { color: #fd7e14; }
        .status-completado { color: #198754; }
        .ticket-card {
            transition: transform 0.2s;
        }
        .ticket-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <?php include_once '../includes/navbar_tecnicos.php'; ?>

    <div class="container my-5">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-tasks"></i> Mis Trabajos</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php foreach ($trabajos as $trabajo): ?>
                                <a href="trabajos.php?ticket_id=<?php echo $trabajo['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $ticket_id == $trabajo['id'] ? 'active' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($trabajo['titulo']); ?></h6>
                                        <span class="status-badge status-<?php echo $trabajo['estado']; ?>">
                                            <?php 
                                                $estados = [
                                                    'pendiente' => 'Pendiente',
                                                    'asignado' => 'Asignado',
                                                    'en_proceso' => 'En Proceso',
                                                    'completado' => 'Completado'
                                                ];
                                                echo $estados[$trabajo['estado']];
                                            ?>
                                        </span>
                                    </div>
                                    <small>
                                        <?php echo htmlspecialchars("{$trabajo['tipo_equipo']} {$trabajo['marca']}"); ?>
                                        <br>
                                        <?php echo htmlspecialchars($trabajo['cliente_nombre']); ?>
                                    </small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle"></i> Trabajos Disponibles</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($tickets_disponibles) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($tickets_disponibles as $ticket): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($ticket['titulo']); ?></h6>
                                            <span class="badge bg-secondary">Disponible</span>
                                        </div>
                                        <small>
                                            <?php echo htmlspecialchars("{$ticket['tipo_equipo']} {$ticket['marca']}"); ?>
                                            <br>
                                            <?php echo htmlspecialchars($ticket['cliente_nombre']); ?>
                                        </small>
                                        <form method="POST" action="" class="mt-2">
                                            <input type="hidden" name="action" value="tomar_trabajo">
                                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success w-100">
                                                <i class="fas fa-hand-paper"></i> Tomar este trabajo
                                            </button>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No hay trabajos disponibles</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <?php if ($ticket_detalle): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Ticket #<?php echo $ticket_detalle['id']; ?> - <?php echo htmlspecialchars($ticket_detalle['titulo']); ?></h5>
                            <span class="status-badge status-<?php echo $ticket_detalle['estado']; ?>">
                                <?php echo $estados[$ticket_detalle['estado']]; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p><strong>Cliente:</strong> <?php echo htmlspecialchars($ticket_detalle['cliente_nombre']); ?></p>
                                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($ticket_detalle['cliente_telefono'] ?: 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Equipo:</strong> <?php echo htmlspecialchars("{$ticket_detalle['tipo_equipo']} {$ticket_detalle['marca']} {$ticket_detalle['modelo']}"); ?></p>
                                    <p><strong>Número de Serie:</strong> <?php echo htmlspecialchars($ticket_detalle['numero_serie'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                            
                            <h6 class="border-bottom pb-2">Descripción del problema</h6>
                            <p><?php echo nl2br(htmlspecialchars($ticket_detalle['descripcion'])); ?></p>
                            
                            <?php if ($ticket_detalle['foto']): ?>
                                <h6 class="border-bottom pb-2 mt-4">Foto del equipo</h6>
                                <img src="../assets/uploads/<?php echo htmlspecialchars($ticket_detalle['foto']); ?>" alt="Foto del equipo" class="img-thumbnail" style="max-height: 200px;">
                            <?php endif; ?>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Información de Asignación</h6>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Fecha de asignación:</strong> <?php echo date('d/m/Y H:i', strtotime($asignacion['fecha_asignacion'])); ?></p>
                                            <?php if ($asignacion['fecha_aceptacion']): ?>
                                                <p><strong>Fecha de aceptación:</strong> <?php echo date('d/m/Y H:i', strtotime($asignacion['fecha_aceptacion'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <?php if ($ticket_detalle['estado'] === 'asignado'): ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="aceptar_trabajo">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-check-circle"></i> Aceptar Trabajo
                                        </button>
                                    </form>
                                <?php elseif ($ticket_detalle['estado'] === 'en_proceso'): ?>
                                    <a href="completar_trabajo.php?id=<?php echo $ticket_detalle['id']; ?>" class="btn btn-success">
                                        <i class="fas fa-check-double"></i> Completar Trabajo
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php elseif ($ticket_id > 0): ?>
                    <div class="alert alert-danger">Ticket no encontrado o no tienes permiso para verlo</div>
                <?php else: ?>
                    <div class="alert alert-info">Selecciona un trabajo para ver sus detalles</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>