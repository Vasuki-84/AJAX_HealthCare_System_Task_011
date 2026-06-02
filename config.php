<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "clinic_db";
$port = 3308; // Added port as per your reference

// Set mysqli to throw exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token will be generated upon successful login
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

try {
    // Create connection with specified port
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
} catch (mysqli_sql_exception $e) {
    header("Content-Type: application/json");
    die(json_encode(["status" => "error", "message" => "Database Connection Failed: " . $e->getMessage()]));
}

?>