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

// Obtener tickets recientes del cliente
$stmt = $conn->prepare("SELECT t.id, t.titulo, t.descripcion, t.estado, t.fecha_creacion, 
                       u.nombre as tecnico_nombre 
                       FROM tickets t
                       LEFT JOIN asignaciones a ON t.id = a.ticket_id
                       LEFT JOIN usuarios u ON a.tecnico_id = u.id
                       WHERE t.cliente_id = ?
                       ORDER BY t.fecha_creacion DESC
                       LIMIT 5");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener técnicos disponibles
$tecnicos = obtenerTecnicosDisponibles();

// Obtener estadísticas
$stmt = $conn->prepare("SELECT 
                       SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                       SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
                       SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados
                       FROM tickets WHERE cliente_id = ?");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Cliente - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .ticket-status {
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-pendiente { color: #6c757d; }
        .status-asignado { color: #0d6efd; }
        .status-en_proceso { color: #fd7e14; }
        .status-completado { color: #198754; }
        .status-cancelado { color: #dc3545; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <?php include_once '../includes/navbar_clientes.php'; ?>

    <div class="container my-5">
        <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?></h2>
        <p class="text-muted">Aquí puedes gestionar todos tus tickets de soporte</p>
        
        <div class="row mt-4">
            <div class="col-md-4 mb-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Tickets Pendientes</h5>
                                <h2 class="mb-0"><?php echo $stats['pendientes'] ?? 0; ?></h2>
                            </div>
                            <i class="fas fa-clock fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">En Proceso</h5>
                                <h2 class="mb-0"><?php echo $stats['en_proceso'] ?? 0; ?></h2>
                            </div>
                            <i class="fas fa-tools fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Completados</h5>
                                <h2 class="mb-0"><?php echo $stats['completados'] ?? 0; ?></h2>
                            </div>
                            <i class="fas fa-check-circle fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-tools"></i> Mis Tickets Recientes</h5>
                            <a href="nuevo_ticket.php" class="btn btn-sm btn-light">
                                <i class="fas fa-plus"></i> Nuevo
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($tickets) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($tickets as $ticket): ?>
                                    <a href="historial.php?ticket_id=<?php echo $ticket['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($ticket['titulo']); ?></h5>
                                            <small class="ticket-status status-<?php echo $ticket['estado']; ?>">
                                                <?php 
                                                    $estados = [
                                                        'pendiente' => 'Pendiente',
                                                        'asignado' => 'Asignado',
                                                        'en_proceso' => 'En Proceso',
                                                        'completado' => 'Completado',
                                                        'cancelado' => 'Cancelado'
                                                    ];
                                                    echo $estados[$ticket['estado']];
                                                ?>
                                            </small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars(substr($ticket['descripcion'], 0, 100)); ?>...</p>
                                        <small>
                                            <?php if ($ticket['tecnico_nombre']): ?>
                                                Técnico: <?php echo htmlspecialchars($ticket['tecnico_nombre']); ?> | 
                                            <?php endif; ?>
                                            <?php echo date('d/m/Y H:i', strtotime($ticket['fecha_creacion'])); ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No hay tickets registrados</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user-cog"></i> Técnicos Disponibles</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($tecnicos) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($tecnico['nombre']); ?></h5>
                                            <span class="badge bg-primary rounded-pill">
                                                <?php echo number_format($tecnico['calificacion_promedio'], 1); ?> ★
                                            </span>
                                        </div>
                                        <p class="mb-1"><strong>Especialidad:</strong> <?php echo htmlspecialchars($tecnico['especialidad']); ?></p>
                                        <small><?php echo htmlspecialchars($tecnico['experiencia']); ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">No hay técnicos disponibles</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>