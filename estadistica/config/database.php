<?php
/**
 * Database configuration file
 * Establishes connection to MySQL database
 */

class Database {
    // Database connection parameters
    private $host = "localhost";
    private $db_name = "quimiosalud";
    private $username = "root";
    private $password = "";
    public $conn;

    // Get database connection
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    // Close database connection
    public function closeConnection() {
        $this->conn = null;
    }
}
?>
