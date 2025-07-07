<?php
// Configuración básica
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'soporte_refrigeracion');

// Configuración de la aplicación
define('APP_NAME', 'Soporte Técnico Refrigeración');
define('APP_ROOT', dirname(dirname(__FILE__)));
define('URL_ROOT', 'http://localhost/soporte-refrigeracion');
define('UPLOAD_DIR', APP_ROOT . '/assets/uploads/');

// Iniciar sesión
session_start();

// Manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>