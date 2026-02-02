<?php
// as/api/db.php
$host = '127.0.0.1';
$db   = 'vvu_scheduler';
$user = 'root';
$pass = ''; // Default XAMPP password
$charset = 'utf8mb4';

// Create connection
try {
    $conn = new mysqli('localhost', $user, $pass, $db);
} catch (mysqli_sql_exception $e) {
    // If localhost fails, try 127.0.0.1 (TCP/IP)
    try {
        $conn = new mysqli('127.0.0.1', $user, $pass, $db);
    } catch (mysqli_sql_exception $e2) {
        error_log("DB Connection Failed: " . $e2->getMessage());
        // Handle gracefully for HTML pages
        if (basename($_SERVER['PHP_SELF']) == 'login.php' || basename($_SERVER['PHP_SELF']) == 'index.php') {
            die("<div style='padding: 20px; color: red; text-align: center; font-family: sans-serif;'>
                    <h2>System Maintenance</h2>
                    <p>The database service is currently unavailable. Please check configuration.</p>
                    <p><small>" . htmlspecialchars($e2->getMessage()) . "</small></p>
                 </div>");
        }
        
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed: ' . $e2->getMessage()]);
        exit;
    }
}
// Check connection (legacy check)
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset($charset);

// Start Session globally for auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
