<?php
/**
 * Authentication API
 * Handles user authentication operations
 */
require_once '../config/config.php';
require_once '../config/database.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// Handle action
switch ($action) {
    case 'login':
        // Process login
        login($db);
        break;
    
    case 'logout':
        // Process logout
        logout();
        break;
    
    case 'check':
        // Check if user is logged in
        checkAuth();
        break;
    
    default:
        // Invalid action
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Process login request
 * 
 * @param PDO $db Database connection
 */
function login($db) {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        return;
    }
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Check if username and password are provided
    if (!isset($data['username']) || !isset($data['password'])) {
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos']);
        return;
    }
    
    $username = $data['username'];
    $password = $data['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos']);
        return;
    }
    
    // Prepare query to check user credentials
    $query = "SELECT id, username, password, name, role FROM users WHERE username = ?";
    $stmt = $db->prepare($query);
    $stmt->bindParam(1, $username);
    $stmt->execute();
    
    // Check if user exists
    if ($stmt->rowCount() == 1) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = $row['id'];
        $name = $row['name'];
        $hashed_password = $row['password'];
        $role = $row['role'];
        
        // Verify password
        if (password_verify($password, $hashed_password)) {
            // Password is correct, start session
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['name'] = $name;
            $_SESSION['user_role'] = $role;
            $_SESSION['last_activity'] = time();
            
            echo json_encode(['success' => true, 'message' => 'Login exitoso', 'user' => [
                'id' => $id,
                'username' => $username,
                'name' => $name,
                'role' => $role
            ]]);
        } else {
            // Password is incorrect
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        }
    } else {
        // User does not exist
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    }
}

/**
 * Process logout request
 */
function logout() {
    // Destroy session
    session_unset();
    session_destroy();
    
    echo json_encode(['success' => true, 'message' => 'Logout exitoso']);
}

/**
 * Check if user is authenticated
 */
function checkAuth() {
    // Check if user is logged in
    if (isLoggedIn()) {
        echo json_encode(['success' => true, 'authenticated' => true, 'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'name' => $_SESSION['name'],
            'role' => $_SESSION['user_role']
        ]]);
    } else {
        echo json_encode(['success' => true, 'authenticated' => false]);
    }
}
?>
