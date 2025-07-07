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
$ticket_id = $_GET['ticket_id'] ?? 0;

// Obtener todos los tickets del cliente
$query = "SELECT t.id, t.titulo, t.estado, t.fecha_creacion, 
          e.tipo_equipo, e.marca, e.modelo,
          u.nombre as tecnico_nombre
          FROM tickets t
          JOIN equipos e ON t.equipo_id = e.id
          LEFT JOIN asignaciones a ON t.id = a.ticket_id
          LEFT JOIN usuarios u ON a.tecnico_id = u.id
          WHERE t.cliente_id = ?
          ORDER BY t.fecha_creacion DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Si se solicita un ticket específico, obtener sus detalles
$ticket_detalle = null;
$reparaciones = [];
$equipo_info = null;

if ($ticket_id > 0) {
    // Detalles del ticket
    $stmt = $conn->prepare("SELECT t.*, e.*, u.nombre as tecnico_nombre
                           FROM tickets t
                           JOIN equipos e ON t.equipo_id = e.id
                           LEFT JOIN asignaciones a ON t.id = a.ticket_id
                           LEFT JOIN usuarios u ON a.tecnico_id = u.id
                           WHERE t.id = ? AND t.cliente_id = ?");
    $stmt->bind_param("ii", $ticket_id, $cliente_id);
    $stmt->execute();
    $ticket_detalle = $stmt->get_result()->fetch_assoc();
    
    if ($ticket_detalle) {
        // Reparaciones realizadas
        $stmt = $conn->prepare("SELECT r.*, u.nombre as tecnico_nombre 
                               FROM reparaciones r
                               JOIN usuarios u ON r.tecnico_id = u.id
                               WHERE r.ticket_id = ?
                               ORDER BY r.fecha_completado DESC");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        $reparaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Definir estados para mostrar
$estados = [
    'pendiente' => 'Pendiente',
    'asignado' => 'Asignado',
    'en_proceso' => 'En Proceso',
    'completado' => 'Completado',
    'cancelado' => 'Cancelado'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Tickets - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .ticket-status {
            font-weight: bold;
        }
        .status-pendiente { color: #6c757d; }
        .status-asignado { color: #0d6efd; }
        .status-en_proceso { color: #fd7e14; }
        .status-completado { color: #198754; }
        .status-cancelado { color: #dc3545; }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #0d6efd;
            border: 3px solid white;
        }
        .factura-btn {
            position: relative;
        }
        .factura-btn .tooltip-text {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .factura-btn:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include_once '../includes/navbar_clientes.php'; ?>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Mis Tickets</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($tickets) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($tickets as $ticket): ?>
                                    <a href="historial.php?ticket_id=<?php echo $ticket['id']; ?>" 
                                       class="list-group-item list-group-item-action <?php echo $ticket_id == $ticket['id'] ? 'active' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($ticket['titulo']); ?></h6>
                                            <small class="ticket-status status-<?php echo $ticket['estado']; ?>">
                                                <?php echo $estados[$ticket['estado']]; ?>
                                            </small>
                                        </div>
                                        <small>
                                            <?php echo htmlspecialchars("{$ticket['tipo_equipo']} {$ticket['marca']}"); ?>
                                            <?php if ($ticket['tecnico_nombre']): ?>
                                                | <?php echo htmlspecialchars($ticket['tecnico_nombre']); ?>
                                            <?php endif; ?>
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
            
            <div class="col-md-8">
                <?php if ($ticket_detalle): ?>
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Detalles del Ticket #<?php echo $ticket_detalle['id']; ?></h5>
                            <span class="ticket-status status-<?php echo $ticket_detalle['estado']; ?>">
                                <?php echo $estados[$ticket_detalle['estado']]; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p><strong>Equipo:</strong> <?php echo htmlspecialchars("{$ticket_detalle['tipo_equipo']} {$ticket_detalle['marca']} {$ticket_detalle['modelo']}"); ?></p>
                                    <p><strong>Número de Serie:</strong> <?php echo htmlspecialchars($ticket_detalle['numero_serie'] ?: 'N/A'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Fecha de creación:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket_detalle['fecha_creacion'])); ?></p>
                                    <?php if ($ticket_detalle['tecnico_nombre']): ?>
                                        <p><strong>Técnico asignado:</strong> <?php echo htmlspecialchars($ticket_detalle['tecnico_nombre']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <h6 class="border-bottom pb-2">Descripción del problema</h6>
                            <p><?php echo nl2br(htmlspecialchars($ticket_detalle['descripcion'])); ?></p>
                            
                            <?php if ($ticket_detalle['foto']): ?>
                                <h6 class="border-bottom pb-2 mt-4">Foto del equipo</h6>
                                <img src="../assets/uploads/<?php echo htmlspecialchars($ticket_detalle['foto']); ?>" alt="Foto del equipo" class="img-thumbnail" style="max-height: 200px;">
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (count($reparaciones) > 0): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Historial de Reparaciones</h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <?php foreach ($reparaciones as $reparacion): ?>
                                        <div class="timeline-item mb-4">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <h6>Reparación #<?php echo $reparacion['id']; ?></h6>
                                                        <small class="text-muted">
                                                            <?php echo date('d/m/Y H:i', strtotime($reparacion['fecha_completado'])); ?>
                                                        </small>
                                                    </div>
                                                    <p class="mb-1"><strong>Técnico:</strong> <?php echo htmlspecialchars($reparacion['tecnico_nombre']); ?></p>
                                                    <p class="mb-1"><strong>Costo:</strong> $<?php echo number_format($reparacion['costo_total'], 2); ?></p>
                                                    
                                                    <div class="accordion mt-3" id="accordionReparacion<?php echo $reparacion['id']; ?>">
                                                        <div class="accordion-item">
                                                            <h2 class="accordion-header">
                                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDiagnostico<?php echo $reparacion['id']; ?>">
                                                                    Diagnóstico
                                                                </button>
                                                            </h2>
                                                            <div id="collapseDiagnostico<?php echo $reparacion['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionReparacion<?php echo $reparacion['id']; ?>">
                                                                <div class="accordion-body">
                                                                    <?php echo nl2br(htmlspecialchars($reparacion['diagnostico'])); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="accordion-item">
                                                            <h2 class="accordion-header">
                                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSolucion<?php echo $reparacion['id']; ?>">
                                                                    Solución Aplicada
                                                                </button>
                                                            </h2>
                                                            <div id="collapseSolucion<?php echo $reparacion['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionReparacion<?php echo $reparacion['id']; ?>">
                                                                <div class="accordion-body">
                                                                    <?php echo nl2br(htmlspecialchars($reparacion['solucion'])); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="accordion-item">
                                                            <h2 class="accordion-header">
                                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRepuestos<?php echo $reparacion['id']; ?>">
                                                                    Repuestos Utilizados
                                                                </button>
                                                            </h2>
                                                            <div id="collapseRepuestos<?php echo $reparacion['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionReparacion<?php echo $reparacion['id']; ?>">
                                                                <div class="accordion-body">
                                                                    <?php echo nl2br(htmlspecialchars($reparacion['repuestos_utilizados'] ?: 'Ninguno')); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php 
                                                    // Verificar si la factura existe físicamente
                                                    if ($reparacion['factura_pdf']):
                                                        $ruta_factura = $_SERVER['DOCUMENT_ROOT'] . '/soporte-refrigeracion/assets/uploads/' . $reparacion['factura_pdf'];
                                                        if (file_exists($ruta_factura)): ?>
                                                            <div class="mt-3 factura-btn">
                                                                <a href="../assets/uploads/<?php echo htmlspecialchars($reparacion['factura_pdf']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                                                    <i class="fas fa-file-pdf"></i> Ver Factura
                                                                    <span class="tooltip-text">Haz clic para ver o descargar la factura en PDF</span>
                                                                </a>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="alert alert-warning mt-3">Factura no disponible</div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <?php 
                                                    // Verificar si ya fue calificada
                                                    $stmt = $conn->prepare("SELECT * FROM calificaciones WHERE reparacion_id = ?");
                                                    $stmt->bind_param("i", $reparacion['id']);
                                                    $stmt->execute();
                                                    $calificacion = $stmt->get_result()->fetch_assoc();
                                                    
                                                    if ($calificacion): ?>
                                                        <div class="mt-3 p-2 bg-light rounded">
                                                            <p class="mb-1"><strong>Tu calificación:</strong> 
                                                                <?php echo str_repeat('★', $calificacion['puntuacion']) . str_repeat('☆', 5 - $calificacion['puntuacion']); ?>
                                                            </p>
                                                            <?php if ($calificacion['comentario']): ?>
                                                                <p class="mb-0"><strong>Comentario:</strong> <?php echo htmlspecialchars($calificacion['comentario']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php elseif ($reparacion['id'] && $ticket_detalle['estado'] === 'completado'): ?>
                                                        <div class="mt-3">
                                                            <a href="calificar.php?id=<?php echo $reparacion['id']; ?>" class="btn btn-sm btn-success">
                                                                <i class="fas fa-star"></i> Calificar este servicio
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($ticket_detalle['estado'] === 'completado'): ?>
                        <div class="alert alert-info">No hay registros de reparación para este ticket</div>
                    <?php endif; ?>
                <?php elseif ($ticket_id > 0): ?>
                    <div class="alert alert-danger">Ticket no encontrado o no tienes permiso para verlo</div>
                <?php else: ?>
                    <div class="alert alert-info">Selecciona un ticket para ver sus detalles</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>