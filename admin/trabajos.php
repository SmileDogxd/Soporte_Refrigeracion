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

$ticket_id = $_GET['ticket_id'] ?? 0;
$estado = $_GET['estado'] ?? 'todos';

// Construir consulta según filtros
$where = "";
$params = [];
$types = "";

if ($estado !== 'todos') {
    $where = "WHERE t.estado = ?";
    $params[] = $estado;
    $types = "s";
}

// Obtener todos los trabajos con filtros
$query = "SELECT t.id, t.titulo, t.estado, t.fecha_creacion, 
          e.tipo_equipo, e.marca, e.modelo,
          u.nombre as cliente_nombre,
          tec.nombre as tecnico_nombre
          FROM tickets t
          JOIN equipos e ON t.equipo_id = e.id
          JOIN usuarios u ON t.cliente_id = u.id
          LEFT JOIN asignaciones a ON t.id = a.ticket_id
          LEFT JOIN usuarios tec ON a.tecnico_id = tec.id
          $where
          ORDER BY t.fecha_creacion DESC";

$stmt = $conn->prepare($query);
if ($where !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$trabajos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Si se solicita un ticket específico, obtener sus detalles
$ticket_detalle = null;
$reparaciones = [];

if ($ticket_id > 0) {
    // Detalles del ticket
    $stmt = $conn->prepare("SELECT t.*, e.*, u.nombre as cliente_nombre, u.telefono as cliente_telefono,
                           tec.nombre as tecnico_nombre, a.fecha_asignacion, a.fecha_aceptacion
                           FROM tickets t
                           JOIN equipos e ON t.equipo_id = e.id
                           JOIN usuarios u ON t.cliente_id = u.id
                           LEFT JOIN asignaciones a ON t.id = a.ticket_id
                           LEFT JOIN usuarios tec ON a.tecnico_id = tec.id
                           WHERE t.id = ?");
    $stmt->bind_param("i", $ticket_id);
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Trabajos - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        .status-badge {
            font-weight: bold;
        }
        .status-pendiente { color: #6c757d; }
        .status-asignado { color: #0d6efd; }
        .status-en_proceso { color: #fd7e14; }
        .status-completado { color: #198754; }
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
    </style>
</head>
<body>
    <?php include_once '../includes/navbar_admin.php'; ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-tasks"></i> Gestión de Trabajos</h2>
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownEstado" data-bs-toggle="dropdown">
                    <?php 
                        $estados_filtro = [
                            'todos' => 'Todos los estados',
                            'pendiente' => 'Pendientes',
                            'asignado' => 'Asignados',
                            'en_proceso' => 'En Proceso',
                            'completado' => 'Completados'
                        ];
                        echo $estados_filtro[$estado];
                    ?>
                </button>
                <ul class="dropdown-menu">
                    <?php foreach ($estados_filtro as $key => $value): ?>
                        <li><a class="dropdown-item" href="trabajos.php?estado=<?php echo $key; ?><?php echo $ticket_id ? '&ticket_id='.$ticket_id : ''; ?>"><?php echo $value; ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Lista de Trabajos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($trabajos) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($trabajos as $trabajo): ?>
                                    <a href="trabajos.php?ticket_id=<?php echo $trabajo['id']; ?><?php echo $estado !== 'todos' ? '&estado='.$estado : ''; ?>" 
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
                                            <?php echo htmlspecialchars($trabajo['cliente_nombre']); ?>
                                            <?php if ($trabajo['tecnico_nombre']): ?>
                                                | <?php echo htmlspecialchars($trabajo['tecnico_nombre']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No hay trabajos registrados</div>
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
                            
                            <?php if ($ticket_detalle['tecnico_nombre']): ?>
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card">
                                            <div class="card-header bg-light">
                                                <h6 class="mb-0">Información del Técnico</h6>
                                            </div>
                                            <div class="card-body">
                                                <p><strong>Técnico:</strong> <?php echo htmlspecialchars($ticket_detalle['tecnico_nombre']); ?></p>
                                                <p><strong>Asignado el:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket_detalle['fecha_asignacion'])); ?></p>
                                                <?php if ($ticket_detalle['fecha_aceptacion']): ?>
                                                    <p><strong>Aceptado el:</strong> <?php echo date('d/m/Y H:i', strtotime($ticket_detalle['fecha_aceptacion'])); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php elseif ($ticket_detalle['estado'] === 'pendiente'): ?>
                                <div class="alert alert-warning mt-4">
                                    <i class="fas fa-exclamation-triangle"></i> Este ticket necesita asignación a un técnico
                                </div>
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
                                                    
                                                    <?php if ($reparacion['factura_pdf']): ?>
                                                        <div class="mt-3">
                                                            <a href="../assets/uploads/<?php echo htmlspecialchars($reparacion['factura_pdf']); ?>" class="btn btn-sm btn-primary" target="_blank">
                                                                <i class="fas fa-file-pdf"></i> Ver Factura
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php 
                                                    // Obtener calificación si existe
                                                    $stmt = $conn->prepare("SELECT c.*, u.nombre as cliente_nombre 
                                                                         FROM calificaciones c
                                                                         JOIN usuarios u ON c.cliente_id = u.id
                                                                         WHERE c.reparacion_id = ?");
                                                    $stmt->bind_param("i", $reparacion['id']);
                                                    $stmt->execute();
                                                    $calificacion = $stmt->get_result()->fetch_assoc();
                                                    
                                                    if ($calificacion): ?>
                                                        <div class="mt-3 p-2 bg-light rounded">
                                                            <p class="mb-1"><strong>Calificación:</strong> 
                                                                <?php echo str_repeat('★', $calificacion['puntuacion']) . str_repeat('☆', 5 - $calificacion['puntuacion']); ?>
                                                                <small class="text-muted">por <?php echo htmlspecialchars($calificacion['cliente_nombre']); ?></small>
                                                            </p>
                                                            <?php if ($calificacion['comentario']): ?>
                                                                <p class="mb-0"><strong>Comentario:</strong> <?php echo htmlspecialchars($calificacion['comentario']); ?></p>
                                                            <?php endif; ?>
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
                    <div class="alert alert-danger">Ticket no encontrado</div>
                <?php else: ?>
                    <div class="alert alert-info">Selecciona un trabajo para ver sus detalles</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>