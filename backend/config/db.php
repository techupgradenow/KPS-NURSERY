<?php
/**
 * Database Configuration
 * KPS Nursery - Database Connection Handler
 * Auto-detects environment (local/production)
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        // Detect environment
        $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isProduction = strpos($serverName, 'kpsnursery') !== false ||
                        strpos($serverName, 'hostinger') !== false;
        $isLocal = ($serverName === 'localhost' || $serverName === '127.0.0.1');

        if ($isProduction) {
            // Production - Hostinger server
            $this->host = 'localhost';
            $this->db_name = 'u282002960_kpsnursery';
            $this->username = 'u282002960_kpsnursery';
            $this->password = 'KpsNusery@123';
        } else {
            // Local development - Use local MySQL (XAMPP)
            $this->host = 'localhost';
            $this->db_name = 'kps_nursery';
            $this->username = 'root';
            $this->password = '';
        }
    }

    /**
     * Get database connection
     * @return PDO
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );

            // Set PDO attributes
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]);
            exit();
        }

        return $this->conn;
    }
}

/**
 * Helper function to get database connection
 * @return PDO
 */
function getDB() {
    $database = new Database();
    return $database->getConnection();
}
?>
