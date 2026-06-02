<?php
require_once 'config.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'login') {
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(["status" => "error", "message" => "Username and password are required"]);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Generate CSRF token upon login
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                echo json_encode(["status" => "success", "message" => "Login successful"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
        }
        $stmt->close();
    } elseif ($action === 'logout') {
        session_destroy();
        echo json_encode(["status" => "success", "message" => "Logged out successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}