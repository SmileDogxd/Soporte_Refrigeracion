<?php
require_once '../includes/auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    header('Location: ../index.php');
    exit();
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><?php echo APP_NAME; ?> - Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-home"></i> Inicio</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUsuarios" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-users"></i> Usuarios
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="clientes.php"><i class="fas fa-user-friends"></i> Clientes</a></li>
                        <li><a class="dropdown-item" href="tecnicos.php"><i class="fas fa-tools"></i> Técnicos</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownAdmin" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($_SESSION['user_nombre']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>