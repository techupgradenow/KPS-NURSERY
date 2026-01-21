<?php
/**
 * Create Admin User
 * Run once and delete this file
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Direct database connection with new credentials
$dbHost = '193.203.184.194';
$dbName = 'u282002960_kidai';
$dbUser = 'u282002960_kidai';
$dbPass = 'Ufcrxq9iwYXuXuyr';

try {
    $db = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Admin credentials
    $username = 'admin';
    $password = 'admin123';
    $name = 'Administrator';
    $email = 'admin@skbakers.in';
    $role = 'super_admin';

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Check if admin already exists
    $stmt = $db->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->execute([$username]);

    if ($stmt->fetch()) {
        // Update existing admin password
        $stmt = $db->prepare("UPDATE admins SET password = ?, name = ?, email = ?, role = ?, is_active = 1 WHERE username = ?");
        $stmt->execute([$hashedPassword, $name, $email, $role, $username]);

        echo json_encode([
            'success' => true,
            'message' => 'Admin password updated successfully!',
            'credentials' => [
                'username' => $username,
                'password' => $password
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        // Insert new admin
        $stmt = $db->prepare("INSERT INTO admins (username, password, name, email, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
        $stmt->execute([$username, $hashedPassword, $name, $email, $role]);

        echo json_encode([
            'success' => true,
            'message' => 'Admin user created successfully!',
            'credentials' => [
                'username' => $username,
                'password' => $password
            ]
        ], JSON_PRETTY_PRINT);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
