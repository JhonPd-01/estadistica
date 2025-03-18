<?php
require_once 'db.php';

/**
 * Authentication class
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login a user
     */
    public function login($username, $password) {
        // Validate input
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Por favor ingrese usuario y contraseña.'
            ];
        }
        
        // Find user
        $user = $this->db->single(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
        
        // Check if user exists and verify password
        if ($user && password_verify($password, $user['password'])) {
            // Update last login time
            $this->db->query(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            return [
                'success' => true,
                'message' => 'Inicio de sesión exitoso.',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'fullname' => $user['fullname'],
                    'role' => $user['role']
                ]
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Usuario o contraseña incorrectos.'
        ];
    }
    
    /**
     * Logout the current user
     */
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Sesión cerrada correctamente.'
        ];
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'fullname' => $_SESSION['fullname'],
            'role' => $_SESSION['role']
        ];
    }
    
    /**
     * Check if current user is admin
     */
    public function isAdmin() {
        return $this->isLoggedIn() && $_SESSION['role'] === 'admin';
    }
    
    /**
     * Create a new user
     */
    public function createUser($username, $password, $fullname, $email, $role = 'user') {
        // Validate input
        if (empty($username) || empty($password) || empty($fullname) || empty($email)) {
            return [
                'success' => false,
                'message' => 'Todos los campos son requeridos.'
            ];
        }
        
        // Check if username already exists
        $existingUser = $this->db->single(
            "SELECT id FROM users WHERE username = ?",
            [$username]
        );
        
        if ($existingUser) {
            return [
                'success' => false,
                'message' => 'El nombre de usuario ya está en uso.'
            ];
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        try {
            $this->db->query(
                "INSERT INTO users (username, password, fullname, email, role) VALUES (?, ?, ?, ?, ?)",
                [$username, $hashedPassword, $fullname, $email, $role]
            );
            
            return [
                'success' => true,
                'message' => 'Usuario creado exitosamente.',
                'user_id' => $this->db->lastInsertId()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear el usuario: ' . $e->getMessage()
            ];
        }
    }
}
