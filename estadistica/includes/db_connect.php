<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection configuration
$host = 'localhost';
$dbname = 'quimiosalud';
$username = 'root';
$password = '';
$charset = 'utf8mb4';

// Connection options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=$charset",
        $username,
        $password,
        $options
    );
} catch (PDOException $e) {
    // If connection fails, display error message
    die("Error de conexión a la base de datos: " . $e->getMessage());
}
