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
    // Create connection with specified port
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
} catch (mysqli_sql_exception $e) {    // $e - store the exception object that is thrown when a connection error occurs, and we can use it to get the error message using $e->getMessage()
    header("Content-Type: application/json");
    die(json_encode(["status" => "error", "message" => "Database Connection Failed: " . $e->getMessage()]));  // $e->getMessage() -Exception object la irukka actual error message retrieve pannudhu.
}

?>