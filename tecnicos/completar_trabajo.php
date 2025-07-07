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

$ticket_id = $_GET['id'] ?? 0;
$tecnico_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Verificar si el técnico está asignado a este ticket y está en proceso
$stmt = $conn->prepare("SELECT t.*, e.*, u.nombre as cliente_nombre
                       FROM tickets t
                       JOIN equipos e ON t.equipo_id = e.id
                       JOIN usuarios u ON t.cliente_id = u.id
                       JOIN asignaciones a ON t.id = a.ticket_id
                       WHERE t.id = ? AND a.tecnico_id = ? AND t.estado = 'en_proceso'");
$stmt->bind_param("ii", $ticket_id, $tecnico_id);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();

if (!$ticket) {
    header('Location: trabajos.php?error=No puedes completar este trabajo');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $diagnostico = trim($_POST['diagnostico']);
    $solucion = trim($_POST['solucion']);
    $repuestos = trim($_POST['repuestos']);
    $horas = (float)$_POST['horas'];
    $costo = (float)$_POST['costo'];
    
    try {
        // Validaciones
        if (empty($diagnostico) || empty($solucion)) {
            throw new Exception("Diagnóstico y solución son requeridos");
        }
        
        if ($horas <= 0 || $costo <= 0) {
            throw new Exception("Horas de trabajo y costo deben ser mayores a cero");
        }
        
        // Registrar la reparación
        $stmt = $conn->prepare("INSERT INTO reparaciones 
                       (ticket_id, tecnico_id, diagnostico, solucion, repuestos_utilizados, horas_trabajo, costo_total) 
                       VALUES (?, ?, ?, ?, ?, ?, ?)");

// Versión 1 (si $repuestos es numérico):
$stmt->bind_param("iissddd", $ticket_id, $tecnico_id, $diagnostico, $solucion, $repuestos, $horas, $costo);

// Versión 2 (si $repuestos es texto - más probable):
$stmt->bind_param("iisssdd", $ticket_id, $tecnico_id, $diagnostico, $solucion, $repuestos, $horas, $costo);

$stmt->execute();
$reparacion_id = $stmt->insert_id;
        
        // Generar factura (simulado)
        $factura_pdf = 'factura_' . $reparacion_id . '.pdf'; // En producción usar una librería como TCPDF
        
        // Actualizar la reparación con el PDF de la factura
        $stmt = $conn->prepare("UPDATE reparaciones SET factura_pdf = ? WHERE id = ?");
        $stmt->bind_param("si", $factura_pdf, $reparacion_id);
        $stmt->execute();
        
        // Actualizar el estado del ticket
        $stmt = $conn->prepare("UPDATE tickets SET estado = 'completado' WHERE id = ?");
        $stmt->bind_param("i", $ticket_id);
        $stmt->execute();
        
        $success = "Trabajo completado exitosamente. Se ha generado la factura.";
    } catch (Exception $e) {
        $error = "Error al completar el trabajo: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Trabajo - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include_once '../includes/navbar_tecnicos.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-check-double"></i> Completar Trabajo</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                            <div class="text-center mt-3">
                                <a href="trabajos.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Volver a Mis Trabajos
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <h5 class="border-bottom pb-2">Información del Ticket</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Cliente:</strong> <?php echo htmlspecialchars($ticket['cliente_nombre']); ?></p>
                                        <p><strong>Equipo:</strong> <?php echo htmlspecialchars("{$ticket['tipo_equipo']} {$ticket['marca']} {$ticket['modelo']}"); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Problema:</strong> <?php echo htmlspecialchars($ticket['titulo']); ?></p>
                                        <p><strong>Descripción:</strong> <?php echo htmlspecialchars(substr($ticket['descripcion'], 0, 100)); ?>...</p>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" action="">
                                <div class="mb-4">
                                    <h5 class="border-bottom pb-2">Detalles de la Reparación</h5>
                                    
                                    <div class="mb-3">
                                        <label for="diagnostico" class="form-label">Diagnóstico *</label>
                                        <textarea class="form-control" id="diagnostico" name="diagnostico" rows="3" required></textarea>
                                        <small class="text-muted">Describe el problema encontrado</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="solucion" class="form-label">Solución Aplicada *</label>
                                        <textarea class="form-control" id="solucion" name="solucion" rows="3" required></textarea>
                                        <small class="text-muted">Describe cómo resolviste el problema</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="repuestos" class="form-label">Repuestos Utilizados</label>
                                        <textarea class="form-control" id="repuestos" name="repuestos" rows="2"></textarea>
                                        <small class="text-muted">Lista los repuestos utilizados, separados por comas</small>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="horas" class="form-label">Horas de Trabajo *</label>
                                            <input type="number" step="0.5" min="0.5" class="form-control" id="horas" name="horas" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="costo" class="form-label">Costo Total ($) *</label>
                                            <input type="number" step="0.01" min="0.01" class="form-control" id="costo" name="costo" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-check-double"></i> Completar Trabajo
                                    </button>
                                    <a href="trabajos.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validar formulario antes de enviar
        document.querySelector('form').addEventListener('submit', function(e) {
            const horas = parseFloat(document.getElementById('horas').value);
            const costo = parseFloat(document.getElementById('costo').value);
            
            if (horas <= 0 || isNaN(horas)) {
                alert('Las horas de trabajo deben ser mayores a cero');
                e.preventDefault();
                return false;
            }
            
            if (costo <= 0 || isNaN(costo)) {
                alert('El costo total debe ser mayor a cero');
                e.preventDefault();
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>