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

// Obtener estadísticas generales
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente' AND activo = 1) as total_clientes,
                (SELECT COUNT(*) FROM usuarios WHERE rol = 'tecnico' AND activo = 1) as total_tecnicos,
                (SELECT COUNT(*) FROM tickets) as total_tickets,
                (SELECT COUNT(*) FROM tickets WHERE estado = 'pendiente') as tickets_pendientes,
                (SELECT COUNT(*) FROM tickets WHERE estado = 'en_proceso') as tickets_proceso,
                (SELECT COUNT(*) FROM tickets WHERE estado = 'completado') as tickets_completados,
                (SELECT AVG(calificacion_promedio) FROM tecnicos) as promedio_calificaciones";
$stats = $conn->query($stats_query)->fetch_assoc();

// Obtener últimos tickets registrados
$tickets_query = "SELECT t.id, t.titulo, t.estado, t.fecha_creacion, 
                 e.tipo_equipo, e.marca,
                 u.nombre as cliente_nombre,
                 tec.nombre as tecnico_nombre
                 FROM tickets t
                 JOIN equipos e ON t.equipo_id = e.id
                 JOIN usuarios u ON t.cliente_id = u.id
                 LEFT JOIN asignaciones a ON t.id = a.ticket_id
                 LEFT JOIN usuarios tec ON a.tecnico_id = tec.id
                 ORDER BY t.fecha_creacion DESC
                 LIMIT 5";
$tickets = $conn->query($tickets_query)->fetch_all(MYSQLI_ASSOC);

// Obtener técnicos mejor calificados
$tecnicos_query = "SELECT u.nombre, t.calificacion_promedio, t.especialidad,
                  (SELECT COUNT(*) FROM asignaciones a WHERE a.tecnico_id = u.id) as trabajos_realizados
                  FROM usuarios u
                  JOIN tecnicos t ON u.id = t.usuario_id
                  WHERE u.activo = 1
                  ORDER BY t.calificacion_promedio DESC
                  LIMIT 3";
$tecnicos = $conn->query($tecnicos_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
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
        .card-hover:hover {
            transform: translateY(-5px);
            transition: transform 0.3s;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <?php include_once '../includes/navbar_admin.php'; ?>

    <div class="container my-5">
        <h2>Panel de Administración</h2>
        <p class="text-muted">Bienvenido, <?php echo htmlspecialchars($_SESSION['user_nombre']); ?></p>
        
        <div class="row mt-4">
            <div class="col-md-3 mb-4">
                <div class="card text-white bg-primary card-hover">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Clientes</h5>
                                <h2 class="mb-0"><?php echo $stats['total_clientes']; ?></h2>
                            </div>
                            <i class="fas fa-users fa-3x"></i>
                        </div>
                        <div class="mt-2">
                            <a href="clientes.php" class="text-white">Ver todos <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card text-white bg-success card-hover">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Técnicos</h5>
                                <h2 class="mb-0"><?php echo $stats['total_tecnicos']; ?></h2>
                            </div>
                            <i class="fas fa-user-cog fa-3x"></i>
                        </div>
                        <div class="mt-2">
                            <a href="tecnicos.php" class="text-white">Ver todos <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card text-white bg-warning card-hover">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Tickets</h5>
                                <h2 class="mb-0"><?php echo $stats['total_tickets']; ?></h2>
                            </div>
                            <i class="fas fa-ticket-alt fa-3x"></i>
                        </div>
                        <div class="mt-2">
                            <a href="trabajos.php" class="text-white">Ver todos <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-4">
                <div class="card text-white bg-info card-hover">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title">Calificación</h5>
                                <h2 class="mb-0">
                                    <?php echo number_format($stats['promedio_calificaciones'], 1); ?>
                                    <i class="fas fa-star rating-star"></i>
                                </h2>
                            </div>
                            <i class="fas fa-star fa-3x rating-star"></i>
                        </div>
                        <div class="mt-2">
                            <a href="reportes.php" class="text-white">Ver reportes <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Estadísticas de Tickets</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ticketsChart" height="200"></canvas>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-ticket-alt"></i> Últimos Tickets Registrados</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Título</th>
                                        <th>Cliente</th>
                                        <th>Equipo</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tickets as $ticket): ?>
                                        <tr onclick="window.location='trabajos.php?ticket_id=<?php echo $ticket['id']; ?>'" style="cursor: pointer;">
                                            <td>#<?php echo $ticket['id']; ?></td>
                                            <td><?php echo htmlspecialchars($ticket['titulo']); ?></td>
                                            <td><?php echo htmlspecialchars($ticket['cliente_nombre']); ?></td>
                                            <td><?php echo htmlspecialchars("{$ticket['tipo_equipo']} {$ticket['marca']}"); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $ticket['estado']; ?>">
                                                    <?php 
                                                        $estados = [
                                                            'pendiente' => 'Pendiente',
                                                            'asignado' => 'Asignado',
                                                            'en_proceso' => 'En Proceso',
                                                            'completado' => 'Completado'
                                                        ];
                                                        echo $estados[$ticket['estado']];
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($ticket['fecha_creacion'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-star"></i> Técnicos Mejor Calificados</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($tecnicos as $tecnico): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h5><?php echo htmlspecialchars($tecnico['nombre']); ?></h5>
                                        <span class="badge bg-primary">
                                            <?php echo number_format($tecnico['calificacion_promedio'], 1); ?> ★
                                        </span>
                                    </div>
                                    <p class="mb-1"><strong>Especialidad:</strong> <?php echo htmlspecialchars($tecnico['especialidad']); ?></p>
                                    <p class="mb-0"><strong>Trabajos:</strong> <?php echo $tecnico['trabajos_realizados']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="tecnicos.php" class="btn btn-sm btn-success">Ver todos los técnicos</a>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header bg-warning text-white">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Tickets Pendientes</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($stats['tickets_pendientes'] > 0): ?>
                            <div class="alert alert-warning">
                                <h4 class="alert-heading"><?php echo $stats['tickets_pendientes']; ?> tickets pendientes</h4>
                                <p>Hay tickets que necesitan asignación a un técnico.</p>
                                <hr>
                                <a href="trabajos.php?estado=pendiente" class="btn btn-sm btn-warning">Asignar tickets</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> No hay tickets pendientes
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Gráfico de tickets
        const ctx = document.getElementById('ticketsChart').getContext('2d');
        const ticketsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pendientes', 'En Proceso', 'Completados'],
                datasets: [{
                    data: [
                        <?php echo $stats['tickets_pendientes']; ?>,
                        <?php echo $stats['tickets_proceso']; ?>,
                        <?php echo $stats['tickets_completados']; ?>
                    ],
                    backgroundColor: [
                        '#6c757d',
                        '#fd7e14',
                        '#198754'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>