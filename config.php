<?php
// config.php - Database Connection

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_NAME', 'clinic_db');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3308);

// Handle connection errors
if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . mysqli_connect_error()
    ]);
    exit();
}

// Set charset to UTF-8
mysqli_set_charset($conn, 'utf8mb4');
?>