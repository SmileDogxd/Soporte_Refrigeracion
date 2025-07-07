<?php
function uploadFile($file, $uploadDir) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception("Tipo de archivo no permitido");
    }
    
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        throw new Exception("El archivo es demasiado grande (máximo 5MB)");
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $destination = $uploadDir . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception("Error al subir el archivo");
    }
    
    return $filename;
}

function generarFactura($reparacion_id) {
    // Lógica para generar una factura en PDF
    // Podrías usar una librería como TCPDF o Dompdf
    
    $filename = 'factura_' . $reparacion_id . '.pdf';
    $filepath = UPLOAD_DIR . $filename;
    
    // Aquí iría el código para generar el PDF...
    
    return $filename;
}

function calcularCalificacionPromedio($tecnico_id) {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT AVG(puntuacion) as promedio FROM calificaciones WHERE tecnico_id = ?");
    $stmt->bind_param("i", $tecnico_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return round($row['promedio'], 2);
}

function obtenerTecnicosDisponibles() {
    $db = new Database();
    $conn = $db->getConnection();
    
    $query = "SELECT u.id, u.nombre, t.especialidad, t.experiencia, t.calificacion_promedio 
              FROM usuarios u 
              JOIN tecnicos t ON u.id = t.usuario_id 
              WHERE u.activo = 1 
              ORDER BY t.calificacion_promedio DESC";
    
    $result = $conn->query($query);
    $tecnicos = [];
    
    while ($row = $result->fetch_assoc()) {
        $tecnicos[] = $row;
    }
    
    return $tecnicos;
}
?>