<?php
/**
 * API Connection Test
 * Tests database connection and environment detection
 * Access: /backend/api/test-connection.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Detect environment
$serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
$isLocalhost = in_array($serverName, ['localhost', '127.0.0.1']) ||
               strpos($serverName, '192.168.') !== false ||
               strpos($serverName, '.local') !== false;

$environment = $isLocalhost ? 'LOCAL' : 'PRODUCTION';

// Test database connection
require_once '../config/db.php';

$dbConnected = false;
$dbError = null;
$tableCount = 0;

try {
    $db = getDB();
    $dbConnected = true;

    // Count tables
    $stmt = $db->query("SHOW TABLES");
    $tableCount = $stmt->rowCount();

    // Get table names
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $dbError = $e->getMessage();
}

// Response
echo json_encode([
    'success' => $dbConnected,
    'message' => $dbConnected ? 'All systems operational' : 'Database connection failed',
    'data' => [
        'environment' => $environment,
        'server_name' => $serverName,
        'database_connected' => $dbConnected,
        'table_count' => $tableCount,
        'tables' => $tables ?? [],
        'php_version' => phpversion(),
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $dbError
    ]
], JSON_PRETTY_PRINT);
?>
