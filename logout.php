<?php
require 'includes/config.php';
require 'includes/auth.php';

$auth = new Auth();

// Destruir la sesión
$auth->logout();

// Redirigir al login
header("Location: " . BASE_URL . "/login.php");
exit;