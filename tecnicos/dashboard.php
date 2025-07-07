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

// Obtener estadísticas del técnico
$stats_query = "SELECT 
                COUNT(*) as total_trabajos,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'en_proceso' THEN 1 ELSE 0 END) as en_proceso,
                SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
                AVG(c.puntuacion) as calificacion_promedio
                FROM asignaciones a
                JOIN tickets t ON a.ticket_id = t.id
                LEFT JOIN calificaciones c ON t.id = c.reparacion_id
                WHERE a.tecnico_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $tecnico_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Obtener trabajos recientes
$trabajos_query = "SELECT t.id, t.titulo, t.estado, t.fecha_creacion, 
                  e.tipo_equipo, e.marca, e.modelo,
                  u.nombre as cliente_nombre
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
                  t.fecha_creacion DESC
                  LIMIT 5";
$stmt = $conn->prepare($trabajos_query);
$stmt->bind_param("i", $tecnico_id);
$stmt->execute();
$trabajos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener calificaciones recientes
$calificaciones_query = "SELECT c.puntuacion, c.comentario, c.fecha_calificacion,
                        t.titulo, u.nombre as cliente_nombre
                        FROM calificaciones c
                        JOIN reparaciones r ON c.reparacion_id = r.id
                        JOIN tickets t ON r.ticket_id = t.id
                        JOIN usuarios u ON c.cliente_id = u.id
                        WHERE r.tecnico_id = ?
                        ORDER BY c.fecha_calificacion DESC
                        LIMIT 3";
$stmt = $conn->prepare($calificaciones_query);
$stmt->bind_param("i", $tecnico_id);
$stmt->execute();
$calificaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Técnico - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            font-size: 0.8rem;
            font-weight: bold;
        }
        .status-pendiente { color: #6c757d; }
        .status-asignado { color: #0d6efd; }
        .status-en_proceso { color: #fd7e14; }
        .status-completado { color: #198754; }
        .rating-star { color: #ffc107; }
    </style>
</head>
<body>
    <?php include_once '../includes/navbar_tecnicos.php'; ?>

    <div class="container my-5">
        <h2>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?></h2>
        <p class="text-muted">Panel de control técnico</p>
        
        <div class="row mt-4">
            <div class="col-md-3 mb-4">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Total Trabajos</h5>
                                <h2 class="mb-0"><?php echo $stats['total_trabajos'] ?? 0; ?></h2>
                            </div>
                            <i class="fas fa-tasks fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Pendientes</h5>
                                <h2 class="mb-0"><?php echo $stats['pendientes'] ?? 0; ?></h2>
                            </div>
                            <i class="fas fa-clock fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card text-white bg-info">
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
            
            <div class="col-md-3 mb-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Calificación</h5>
                                <h2 class="mb-0">
                                    <?php echo $stats['calificacion_promedio'] ? number_format($stats['calificacion_promedio'], 1) : 'N/A'; ?>
                                    <?php if ($stats['calificacion_promedio']): ?>
                                        <i class="fas fa-star rating-star"></i>
                                    <?php endif; ?>
                                </h2>
                            </div>
                            <i class="fas fa-star fa-3x rating-star"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-tasks"></i> Mis Trabajos Recientes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($trabajos) > 0): ?>
                            <div class="list-group">
                                <?php foreach ($trabajos as $trabajo): ?>
                                    <a href="trabajos.php?ticket_id=<?php echo $trabajo['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($trabajo['titulo']); ?></h5>
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
                                        <p class="mb-1">
                                            <?php echo htmlspecialchars("{$trabajo['tipo_equipo']} {$trabajo['marca']}"); ?>
                                            <br>
                                            <small>Cliente: <?php echo htmlspecialchars($trabajo['cliente_nombre']); ?></small>
                                        </p>
                                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($trabajo['fecha_creacion'])); ?></small>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-3 text-center">
                                <a href="trabajos.php" class="btn btn-primary">Ver todos los trabajos</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No tienes trabajos asignados</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-star"></i> Mis Calificaciones Recientes</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($calificaciones) > 0): ?>
                            <?php foreach ($calificaciones as $calificacion): ?>
                                <div class="card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <h5><?php echo str_repeat('★', $calificacion['puntuacion']) . str_repeat('☆', 5 - $calificacion['puntuacion']); ?></h5>
                                            <small class="text-muted"><?php echo date('d/m/Y', strtotime($calificacion['fecha_calificacion'])); ?></small>
                                        </div>
                                        <p class="mb-1"><strong>Trabajo:</strong> <?php echo htmlspecialchars($calificacion['titulo']); ?></p>
                                        <p class="mb-1"><strong>Cliente:</strong> <?php echo htmlspecialchars($calificacion['cliente_nombre']); ?></p>
                                        <?php if ($calificacion['comentario']): ?>
                                            <div class="mt-2 p-2 bg-light rounded">
                                                <p class="mb-0"><i class="fas fa-quote-left"></i> <?php echo htmlspecialchars($calificacion['comentario']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No tienes calificaciones aún</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>