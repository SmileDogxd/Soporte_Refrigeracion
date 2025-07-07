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
$error = '';
$success = '';

// Obtener equipos del cliente para seleccionar
$stmt = $conn->prepare("SELECT id, tipo_equipo, marca, modelo FROM equipos WHERE cliente_id = ?");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$equipos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipo_id = $_POST['equipo_id'] ?? 0;
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $prioridad = $_POST['prioridad'] ?? 'media';
    
    // Validar si es equipo nuevo
    $nuevo_equipo = isset($_POST['nuevo_equipo']) && $_POST['nuevo_equipo'] === '1';
    
    try {
        // Si es equipo nuevo, registrarlo primero
        if ($nuevo_equipo) {
            $tipo_equipo = $_POST['tipo_equipo'];
            $marca = $_POST['marca'];
            $modelo = $_POST['modelo'];
            $numero_serie = $_POST['numero_serie'];
            $fecha_compra = $_POST['fecha_compra'];
            
            $stmt = $conn->prepare("INSERT INTO equipos (cliente_id, tipo_equipo, marca, modelo, numero_serie, fecha_compra) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $cliente_id, $tipo_equipo, $marca, $modelo, $numero_serie, $fecha_compra);
            $stmt->execute();
            $equipo_id = $stmt->insert_id;
        }
        
        // Validar que tenemos un equipo_id válido
        if ($equipo_id <= 0) {
            throw new Exception("Debes seleccionar o registrar un equipo");
        }
        
        // Manejar la subida de la foto
        $foto = '';
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $foto = uploadFile($_FILES['foto'], UPLOAD_DIR);
        }
        
        // Crear el ticket
        $stmt = $conn->prepare("INSERT INTO tickets (equipo_id, cliente_id, titulo, descripcion, foto, prioridad) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $equipo_id, $cliente_id, $titulo, $descripcion, $foto, $prioridad);
        $stmt->execute();
        
        $success = "Ticket creado exitosamente. Un técnico se pondrá en contacto pronto.";
        $_POST = array(); // Limpiar el formulario
    } catch (Exception $e) {
        $error = "Error al crear el ticket: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Ticket - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include_once '../includes/navbar_clientes.php'; ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-plus-circle"></i> Nuevo Ticket de Soporte</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                            <div class="text-center mt-3">
                                <a href="dashboard.php" class="btn btn-primary me-2">
                                    <i class="fas fa-home"></i> Volver al Inicio
                                </a>
                                <a href="nuevo_ticket.php" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Crear Otro Ticket
                                </a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" enctype="multipart/form-data">
                                <div class="mb-4">
                                    <h5 class="border-bottom pb-2">1. Seleccionar Equipo</h5>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="equipo_opcion" id="equipo_existente" value="existente" checked>
                                        <label class="form-check-label" for="equipo_existente">
                                            Seleccionar equipo existente
                                        </label>
                                    </div>
                                    
                                    <div id="equipo-existente-container">
                                        <select class="form-select mb-3" name="equipo_id" id="equipo_id">
                                            <option value="">Seleccione un equipo...</option>
                                            <?php foreach ($equipos as $equipo): ?>
                                                <option value="<?php echo $equipo['id']; ?>">
                                                    <?php echo htmlspecialchars("{$equipo['tipo_equipo']} {$equipo['marca']} {$equipo['modelo']}"); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="equipo_opcion" id="equipo_nuevo" value="nuevo">
                                        <label class="form-check-label" for="equipo_nuevo">
                                            Registrar nuevo equipo
                                        </label>
                                        <input type="hidden" name="nuevo_equipo" id="nuevo_equipo" value="0">
                                    </div>
                                    
                                    <div id="equipo-nuevo-container" style="display: none;">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="tipo_equipo" class="form-label">Tipo de Equipo *</label>
                                                <select class="form-select" id="tipo_equipo" name="tipo_equipo">
                                                    <option value="">Seleccione...</option>
                                                    <option value="Refrigerador">Refrigerador</option>
                                                    <option value="Congelador">Congelador</option>
                                                    <option value="Aire Acondicionado">Aire Acondicionado</option>
                                                    <option value="Sistema de Refrigeración Comercial">Sistema de Refrigeración Comercial</option>
                                                    <option value="Otro">Otro</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="marca" class="form-label">Marca *</label>
                                                <input type="text" class="form-control" id="marca" name="marca">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="modelo" class="form-label">Modelo *</label>
                                                <input type="text" class="form-control" id="modelo" name="modelo">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="numero_serie" class="form-label">Número de Serie</label>
                                                <input type="text" class="form-control" id="numero_serie" name="numero_serie">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="fecha_compra" class="form-label">Fecha de Compra</label>
                                                <input type="date" class="form-control" id="fecha_compra" name="fecha_compra">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5 class="border-bottom pb-2">2. Descripción del Problema</h5>
                                    <div class="mb-3">
                                        <label for="titulo" class="form-label">Título del Problema *</label>
                                        <input type="text" class="form-control" id="titulo" name="titulo" 
                                               value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descripcion" class="form-label">Descripción Detallada *</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="5" required><?php echo htmlspecialchars($_POST['descripcion'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="prioridad" class="form-label">Prioridad *</label>
                                            <select class="form-select" id="prioridad" name="prioridad" required>
                                                <option value="baja" <?php echo ($_POST['prioridad'] ?? '') === 'baja' ? 'selected' : ''; ?>>Baja</option>
                                                <option value="media" <?php echo ($_POST['prioridad'] ?? 'media') === 'media' ? 'selected' : ''; ?>>Media</option>
                                                <option value="alta" <?php echo ($_POST['prioridad'] ?? '') === 'alta' ? 'selected' : ''; ?>>Alta</option>
                                                <option value="urgente" <?php echo ($_POST['prioridad'] ?? '') === 'urgente' ? 'selected' : ''; ?>>Urgente</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="foto" class="form-label">Foto del Equipo (Opcional)</label>
                                            <input class="form-control" type="file" id="foto" name="foto" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane"></i> Enviar Ticket
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary btn-lg">
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
    <script>
        $(document).ready(function() {
            // Mostrar/ocultar formulario de equipo nuevo
            $('input[name="equipo_opcion"]').change(function() {
                if ($(this).val() === 'nuevo') {
                    $('#equipo-existente-container').hide();
                    $('#equipo-nuevo-container').show();
                    $('#nuevo_equipo').val('1');
                    $('#equipo_id').val('').prop('disabled', true);
                } else {
                    $('#equipo-existente-container').show();
                    $('#equipo-nuevo-container').hide();
                    $('#nuevo_equipo').val('0');
                    $('#equipo_id').prop('disabled', false);
                }
            });
            
            // Validar formulario antes de enviar
            $('form').submit(function() {
                if ($('#nuevo_equipo').val() === '1') {
                    if ($('#tipo_equipo').val() === '' || $('#marca').val() === '' || $('#modelo').val() === '') {
                        alert('Por favor complete todos los campos obligatorios del equipo');
                        return false;
                    }
                } else if ($('#equipo_id').val() === '') {
                    alert('Por favor seleccione un equipo');
                    return false;
                }
                return true;
            });
        });
    </script>
</body>
</html>