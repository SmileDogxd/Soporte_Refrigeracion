<?php
require_once 'db.php';
require_once 'functions.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function login($email, $password, $rol) {
        $conn = $this->db->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ? AND rol = ? AND activo = 1");
        $stmt->bind_param("ss", $email, $rol);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_nombre'] = $user['nombre'];
                $_SESSION['user_rol'] = $user['rol'];
                
                return true;
            }
        }
        
        return false;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getUserRole() {
        return $_SESSION['user_rol'] ?? null;
    }

    public function redirectByRole() {
        if ($this->isLoggedIn()) {
            $role = $this->getUserRole();
            
            switch ($role) {
                case 'admin':
                    header('Location: ' . URL_ROOT . '/admin/dashboard.php');
                    break;
                case 'tecnico':
                    header('Location: ' . URL_ROOT . '/tecnicos/dashboard.php');
                    break;
                case 'cliente':
                    header('Location: ' . URL_ROOT . '/clientes/dashboard.php');
                    break;
                default:
                    header('Location: ' . URL_ROOT . '/index.php');
                    break;
            }
            exit();
        }
    }

    public function logout() {
        session_unset();
        session_destroy();
        header('Location: ' . URL_ROOT . '/index.php');
        exit();
    }

    public function registerClient($nombre, $email, $password, $telefono, $direccion) {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verificar si el email ya existe
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception("El email ya está registrado");
    }
    
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $rol = 'cliente';
    
    $stmt = $conn->prepare("INSERT INTO usuarios (nombre, email, password, telefono, direccion, rol) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $nombre, $email, $hashed_password, $telefono, $direccion, $rol);
    
    return $stmt->execute();
}
}
?>