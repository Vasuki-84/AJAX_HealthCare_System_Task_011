<?php
// Store the required values for database connection
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "clinic_db";
$port = 3308; 

// Set mysqli to throw exceptions when errors occur in MYSQL operations, which allows us to catch them and return JSON responses without this it shows only warning
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);


function get_connection($host, $user, $pass, $dbname, $port) {
    try {
        // Try connecting to the server first (without database name)
        $temp_conn = new mysqli($host, $user, $pass, null, $port);
        $temp_conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
        $temp_conn->close();

        // Now connect to the database
        return new mysqli($host, $user, $pass, $dbname, $port);
    } catch (mysqli_sql_exception $e) {
        throw $e;
    }
}

try {
    $conn = get_connection($host, $user, $pass, $dbname, 3308);
} catch (mysqli_sql_exception $e) {
    try {
        $conn = get_connection($host, $user, $pass, $dbname, 3306);
    } catch (mysqli_sql_exception $e2) {
        header("Content-Type: application/json");
        die(json_encode(["status" => "error", "message" => "Database Connection Failed: " . $e2->getMessage()]));
    }
}