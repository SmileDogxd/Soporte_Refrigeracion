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

// Obtener parámetros de filtro
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');
$tecnico_id = $_GET['tecnico_id'] ?? 'todos';

// Construir consulta para reporte de trabajos
$where = "WHERE r.fecha_completado BETWEEN ? AND ?";
$params = [$fecha_inicio, $fecha_fin];
$types = "ss";

if ($tecnico_id !== 'todos') {
    $where .= " AND r.tecnico_id = ?";
    $params[] = $tecnico_id;
    $types .= "i";
}

$query = "SELECT r.*, t.titulo, t.estado,
          e.tipo_equipo, e.marca, e.modelo,
          u.nombre as cliente_nombre,
          tec.nombre as tecnico_nombre
          FROM reparaciones r
          JOIN tickets t ON r.ticket_id = t.id
          JOIN equipos e ON t.equipo_id = e.id
          JOIN usuarios u ON t.cliente_id = u.id
          JOIN usuarios tec ON r.tecnico_id = tec.id
          $where
          ORDER BY r.fecha_completado DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$reparaciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener técnicos para el filtro
$tecnicos_query = "SELECT u.id, u.nombre 
                  FROM usuarios u
                  JOIN tecnicos t ON u.id = t.usuario_id
                  WHERE u.activo = 1
                  ORDER BY u.nombre";
$tecnicos = $conn->query($tecnicos_query)->fetch_all(MYSQLI_ASSOC);

// Calcular estadísticas
$stats_query = "SELECT 
               COUNT(*) as total_reparaciones,
               SUM(r.costo_total) as ingresos_totales,
               AVG(r.costo_total) as promedio_por_trabajo,
               AVG(r.horas_trabajo) as promedio_horas,
               AVG(c.puntuacion) as promedio_calificaciones
               FROM reparaciones r
               LEFT JOIN calificaciones c ON r.id = c.reparacion_id
               $where";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Obtener reparaciones por técnico para el gráfico
$reparaciones_por_tecnico_query = "SELECT tec.nombre, COUNT(*) as cantidad, SUM(r.costo_total) as ingresos
                                  FROM reparaciones r
                                  JOIN usuarios tec ON r.tecnico_id = tec.id
                                  WHERE r.fecha_completado BETWEEN ? AND ?
                                  GROUP BY tec.nombre
                                  ORDER BY cantidad DESC";
$stmt = $conn->prepare($reparaciones_por_tecnico_query);
$stmt->bind_param("ss", $fecha_inicio, $fecha_fin);
$stmt->execute();
$reparaciones_por_tecnico = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Obtener todas las calificaciones para evitar consultas en el bucle
$calificaciones_query = "SELECT reparacion_id, puntuacion FROM calificaciones";
$calificaciones_result = $conn->query($calificaciones_query);
$calificaciones = [];
while ($row = $calificaciones_result->fetch_assoc()) {
    $calificaciones[$row['reparacion_id']] = $row['puntuacion'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            transition: transform 0.3s;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .rating-star { color: #ffc107; }
    </style>
</head>
<body>
    <?php include_once '../includes/navbar_admin.php'; ?>

    <div class="container my-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-chart-bar"></i> Reportes de Trabajos</h2>
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimir Reporte
            </button>
        </div>
        
        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="reportes.php" class="row g-3">
                    <div class="col-md-3">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" class="form-control" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="tecnico_id" class="form-label">Técnico</label>
                        <select class="form-select" id="tecnico_id" name="tecnico_id">
                            <option value="todos">Todos los técnicos</option>
                            <?php foreach ($tecnicos as $tecnico): ?>
                                <option value="<?php echo $tecnico['id']; ?>" <?php echo $tecnico_id == $tecnico['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tecnico['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-primary card-hover">
                    <div class="card-body">
                        <h5 class="card-title">Reparaciones</h5>
                        <h2 class="mb-0"><?php echo $stats['total_reparaciones']; ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-success card-hover">
                    <div class="card-body">
                        <h5 class="card-title">Ingresos Totales</h5>
                        <h2 class="mb-0">$<?php echo number_format($stats['ingresos_totales'], 2); ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-info card-hover">
                    <div class="card-body">
                        <h5 class="card-title">Promedio por Trabajo</h5>
                        <h2 class="mb-0">$<?php echo number_format($stats['promedio_por_trabajo'], 2); ?></h2>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-warning card-hover">
                    <div class="card-body">
                        <h5 class="card-title">Calificación</h5>
                        <h2 class="mb-0">
                            <?php echo $stats['promedio_calificaciones'] ? number_format($stats['promedio_calificaciones'], 1) : 'N/A'; ?>
                            <?php if ($stats['promedio_calificaciones']): ?>
                                <i class="fas fa-star rating-star"></i>
                            <?php endif; ?>
                        </h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráficos -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Reparaciones por Técnico</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="tecnicosChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Ingresos por Técnico</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="ingresosChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lista de reparaciones -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Detalle de Reparaciones</h5>
            </div>
            <div class="card-body">
                <?php if (count($reparaciones) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fecha</th>
                                    <th>Técnico</th>
                                    <th>Cliente</th>
                                    <th>Equipo</th>
                                    <th>Horas</th>
                                    <th>Costo</th>
                                    <th>Calificación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reparaciones as $reparacion): ?>
                                    <tr>
                                        <td><?php echo $reparacion['id']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($reparacion['fecha_completado'])); ?></td>
                                        <td><?php echo htmlspecialchars($reparacion['tecnico_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($reparacion['cliente_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars("{$reparacion['tipo_equipo']} {$reparacion['marca']}"); ?></td>
                                        <td><?php echo number_format($reparacion['horas_trabajo'], 1); ?></td>
                                        <td>$<?php echo number_format($reparacion['costo_total'], 2); ?></td>
                                        <td>
                                            <?php if (isset($calificaciones[$reparacion['id']])): ?>
                                                <?php echo str_repeat('★', $calificaciones[$reparacion['id']]) . str_repeat('☆', 5 - $calificaciones[$reparacion['id']]); ?>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="trabajos.php?ticket_id=<?php echo $reparacion['ticket_id']; ?>" class="btn btn-sm btn-primary" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (!empty($reparacion['factura_pdf'])): ?>
                                                <a href="../uploads/<?php echo htmlspecialchars($reparacion['factura_pdf']); ?>" class="btn btn-sm btn-success" title="Descargar factura" download>
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No hay reparaciones registradas en el período seleccionado.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Datos para gráficos
        const tecnicosData = <?php echo json_encode(array_column($reparaciones_por_tecnico, 'nombre')); ?>;
        const reparacionesData = <?php echo json_encode(array_column($reparaciones_por_tecnico, 'cantidad')); ?>;
        const ingresosData = <?php echo json_encode(array_column($reparaciones_por_tecnico, 'ingresos')); ?>;

        // Gráfico de reparaciones por técnico
        new Chart(document.getElementById('tecnicosChart'), {
            type: 'pie',
            data: {
                labels: tecnicosData,
                datasets: [{
                    data: reparacionesData,
                    backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b']
                }]
            }
        });

        // Gráfico de ingresos por técnico
        new Chart(document.getElementById('ingresosChart'), {
            type: 'bar',
            data: {
                labels: tecnicosData,
                datasets: [{
                    label: 'Ingresos ($)',
                    data: ingresosData,
                    backgroundColor: '#1cc88a'
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>