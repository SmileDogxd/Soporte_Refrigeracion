<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'functions.php';
require_once '../vendor/autoload.php';

$auth = new Auth();
$db = new Database();
$conn = $db->getConnection();

if (!$auth->isLoggedIn() || !in_array($auth->getUserRole(), ['tecnico', 'admin'])) {
    header('Location: ../index.php');
    exit();
}

$reparacion_id = $_GET['id'] ?? 0;

// Obtener datos de la reparación
$query = "SELECT r.*, t.titulo, t.descripcion as problema, 
          e.tipo_equipo, e.marca, e.modelo,
          u.nombre as cliente_nombre, u.direccion as cliente_direccion,
          tec.nombre as tecnico_nombre
          FROM reparaciones r
          JOIN tickets t ON r.ticket_id = t.id
          JOIN equipos e ON t.equipo_id = e.id
          JOIN usuarios u ON t.cliente_id = u.id
          JOIN usuarios tec ON r.tecnico_id = tec.id
          WHERE r.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $reparacion_id);
$stmt->execute();
$reparacion = $stmt->get_result()->fetch_assoc();

if (!$reparacion) {
    die("Reparación no encontrada");
}

// Crear PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor(APP_NAME);
$pdf->SetTitle('Factura #' . $reparacion_id);
$pdf->SetSubject('Factura de Reparación');

// Eliminar header y footer por defecto
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Añadir página
$pdf->AddPage();

// Contenido HTML de la factura
$html = '
<style>
    .header { text-align: center; margin-bottom: 20px; }
    .title { font-size: 18px; font-weight: bold; }
    .info { margin-bottom: 15px; }
    .table { width: 100%; border-collapse: collapse; }
    .table th { background-color: #f2f2f2; text-align: left; padding: 5px; }
    .table td { padding: 5px; border-bottom: 1px solid #ddd; }
    .total { font-weight: bold; text-align: right; }
</style>

<div class="header">
    <h1 class="title">' . APP_NAME . '</h1>
    <p>Factura de Reparación #' . $reparacion_id . '</p>
</div>

<div class="info">
    <p><strong>Fecha:</strong> ' . date('d/m/Y', strtotime($reparacion['fecha_completado'])) . '</p>
    <p><strong>Cliente:</strong> ' . $reparacion['cliente_nombre'] . '</p>
    <p><strong>Dirección:</strong> ' . $reparacion['cliente_direccion'] . '</p>
    <p><strong>Técnico:</strong> ' . $reparacion['tecnico_nombre'] . '</p>
</div>

<h3>Detalles de la Reparación</h3>
<table class="table">
    <tr>
        <th>Equipo</th>
        <td>' . $reparacion['tipo_equipo'] . ' ' . $reparacion['marca'] . ' ' . $reparacion['modelo'] . '</td>
    </tr>
    <tr>
        <th>Problema reportado</th>
        <td>' . $reparacion['problema'] . '</td>
    </tr>
    <tr>
        <th>Diagnóstico</th>
        <td>' . $reparacion['diagnostico'] . '</td>
    </tr>
    <tr>
        <th>Solución aplicada</th>
        <td>' . $reparacion['solucion'] . '</td>
    </tr>
    <tr>
        <th>Repuestos utilizados</th>
        <td>' . ($reparacion['repuestos_utilizados'] ?: 'Ninguno') . '</td>
    </tr>
</table>

<h3>Detalles de Pago</h3>
<table class="table">
    <tr>
        <th>Horas de trabajo</th>
        <td>' . $reparacion['horas_trabajo'] . ' horas</td>
    </tr>
    <tr>
        <th>Costo por hora</th>
        <td>$' . number_format($reparacion['costo_total'] / $reparacion['horas_trabajo'], 2) . '</td>
    </tr>
    <tr>
        <th>Total</th>
        <td>$' . number_format($reparacion['costo_total'], 2) . '</td>
    </tr>
</table>

<p style="margin-top: 30px; text-align: center;">Gracias por confiar en nuestros servicios</p>
';

$pdf->writeHTML($html, true, false, true, false, '');

// Guardar el archivo
$nombre_archivo = 'factura_' . $reparacion_id . '.pdf';
$ruta_archivo = $_SERVER['DOCUMENT_ROOT'] . '/soporte-refrigeracion/assets/uploads/' . $nombre_archivo;

// Crear directorio si no existe
if (!file_exists(dirname($ruta_archivo))) {
    mkdir(dirname($ruta_archivo), 0777, true);
}

$pdf->Output($ruta_archivo, 'F');

// Actualizar la base de datos con el nombre del archivo
$stmt = $conn->prepare("UPDATE reparaciones SET factura_pdf = ? WHERE id = ?");
$stmt->bind_param("si", $nombre_archivo, $reparacion_id);
$stmt->execute();

// Redirigir al PDF generado
header('Location: ../assets/uploads/' . $nombre_archivo);
exit;