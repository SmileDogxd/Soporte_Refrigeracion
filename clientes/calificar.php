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

$reparacion_id = $_GET['id'] ?? 0;
$cliente_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Verificar que esta reparación pertenece al cliente
$stmt = $conn->prepare("SELECT r.*, t.id as ticket_id, u.nombre as tecnico_nombre 
                        FROM reparaciones r
                        JOIN tickets t ON r.ticket_id = t.id
                        JOIN usuarios u ON r.tecnico_id = u.id
                        WHERE r.id = ? AND t.cliente_id = ?");
$stmt->bind_param("ii", $reparacion_id, $cliente_id);
$stmt->execute();
$reparacion = $stmt->get_result()->fetch_assoc();

if (!$reparacion) {
    header('Location: historial.php?error=Reparación no encontrada');
    exit();
}

// Verificar si ya fue calificada
$stmt = $conn->prepare("SELECT * FROM calificaciones WHERE reparacion_id = ?");
$stmt->bind_param("i", $reparacion_id);
$stmt->execute();
$calificacion_existente = $stmt->get_result()->fetch_assoc();

if ($calificacion_existente) {
    header('Location: historial.php?ticket_id=' . $reparacion['ticket_id'] . '&error=Esta reparación ya fue calificada');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $puntuacion = $_POST['puntuacion'];
    $comentario = trim($_POST['comentario']);
    
    try {
        // Registrar la calificación
        $stmt = $conn->prepare("INSERT INTO calificaciones (reparacion_id, tecnico_id, cliente_id, puntuacion, comentario) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiis", $reparacion_id, $reparacion['tecnico_id'], $cliente_id, $puntuacion, $comentario);
        $stmt->execute();
        
        // Actualizar el promedio del técnico
        $nuevo_promedio = calcularCalificacionPromedio($reparacion['tecnico_id']);
        
        $stmt = $conn->prepare("UPDATE tecnicos SET calificacion_promedio = ? WHERE usuario_id = ?");
        $stmt->bind_param("di", $nuevo_promedio, $reparacion['tecnico_id']);
        $stmt->execute();
        
        $success = "Calificación registrada exitosamente. Gracias por tu feedback.";
    } catch (Exception $e) {
        $error = "Error al registrar la calificación: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calificar Servicio - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.css">
</head>
<body>
    <?php include_once '../includes/navbar_clientes.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-star"></i> Calificar Servicio</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                            <div class="text-center mt-3">
                                <a href="historial.php?ticket_id=<?php echo $reparacion['ticket_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-arrow-left"></i> Volver al Historial
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="mb-4">
                                <h5 class="border-bottom pb-2">Detalles de la Reparación</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Técnico:</strong> <?php echo htmlspecialchars($reparacion['tecnico_nombre']); ?></p>
                                        <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($reparacion['fecha_completado'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Diagnóstico:</strong> <?php echo htmlspecialchars(substr($reparacion['diagnostico'], 0, 100)); ?>...</p>
                                        <p><strong>Costo:</strong> $<?php echo number_format($reparacion['costo_total'], 2); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="POST" action="">
                                <div class="mb-4">
                                    <h5 class="border-bottom pb-2">Tu Calificación</h5>
                                    <div class="text-center mb-3">
                                        <div id="rateYo"></div>
                                        <input type="hidden" id="puntuacion" name="puntuacion" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="comentario" class="form-label">Comentario (Opcional)</label>
                                        <textarea class="form-control" id="comentario" name="comentario" rows="3" placeholder="¿Cómo fue tu experiencia con el servicio?"></textarea>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-star"></i> Enviar Calificación
                                    </button>
                                    <a href="historial.php?ticket_id=<?php echo $reparacion['ticket_id']; ?>" class="btn btn-secondary btn-lg">
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/rateYo/2.3.2/jquery.rateyo.min.js"></script>
    <script>
        $(document).ready(function() {
            $("#rateYo").rateYo({
                rating: 0,
                starWidth: "40px",
                fullStar: true,
                onSet: function (rating, rateYoInstance) {
                    $("#puntuacion").val(rating);
                }
            });
            
            // Validar que se haya seleccionado una puntuación
            $("form").submit(function(e) {
                if ($("#puntuacion").val() === "") {
                    e.preventDefault();
                    alert("Por favor selecciona una puntuación");
                }
            });
        });
    </script>
</body>
</html>