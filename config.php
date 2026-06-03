<?php
// Store the required values for database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "clinic_db";
$port = 3308; 

// Set mysqli to throw exceptions when errors occur in MYSQL operations, which allows us to catch them and return JSON responses without this it shows only warning
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


try {
    // Try connection with port 3308 first
    $conn = new mysqli($host, $user, $pass, $dbname, 3308);
} catch (mysqli_sql_exception $e) {
    try {
        // Fallback to default port 3306 if 3308 fails
        $conn = new mysqli($host, $user, $pass, $dbname, 3306);
    } catch (mysqli_sql_exception $e2) {
        header("Content-Type: application/json");
        die(json_encode(["status" => "error", "message" => "Database Connection Failed: " . $e2->getMessage()]));
    }
}

?>