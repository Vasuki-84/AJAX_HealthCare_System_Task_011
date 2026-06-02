<?php
header("Content-Type: application/json");
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Authentication check
if (!isLoggedIn()) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please login."]);
    exit;
}

// CSRF Protection for state-changing methods
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $headers = getallheaders();
    $requestToken = $headers['X-CSRF-TOKEN'] ?? '';
    if (empty($requestToken) || $requestToken !== ($_SESSION['csrf_token'] ?? '')) {
        echo json_encode(["status" => "error", "message" => "Invalid CSRF token"]);
        exit;
    }
}

switch ($method) {
    case 'GET':
        handleGet($conn);
        break;
    case 'POST':
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['id']) && !empty($data['id'])) {
            handleUpdate($conn, $data);
        } else {
            handleCreate($conn, $data);
        }
        break;
    case 'PUT':
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['status_only']) && $data['status_only'] === true) {
            handleStatusUpdate($conn, $data);
        } else {
            handleUpdate($conn, $data);
        }
        break;
    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"), true);
        handleDelete($conn, $data);
        break;
    default:
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        break;
}

function handleGet($conn) {
    $sql = "SELECT * FROM appointments ORDER BY id DESC";
    $result = $conn->query($sql);
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    echo json_encode($appointments);
}

function handleCreate($conn, $data) {
    if (!validate($data)) {
        echo json_encode(["status" => "error", "message" => "Validation failed"]);
        return;
    }

    // 1. Prevent Double Booking (Same Doctor, Date, Time)
    $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_name = ? AND appointment_date = ? AND appointment_time = ? AND status != 'Cancelled'");
    $stmt->bind_param("sss", $data['doctor_name'], $data['appointment_date'], $data['appointment_time']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "This time slot is already booked for " . $data['doctor_name']]);
        $stmt->close();
        return;
    }
    $stmt->close();

    // 2. Appointment Limit Per Day (e.g., 10 per day)
    $limit = 10;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND status != 'Cancelled'");
    $stmt->bind_param("s", $data['appointment_date']);
    $stmt->execute();
    $countResult = $stmt->get_result()->fetch_assoc();
    if ($countResult['count'] >= $limit) {
        echo json_encode(["status" => "error", "message" => "Daily appointment limit ($limit) reached for this date."]);
        $stmt->close();
        return;
    }
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO appointments (patient_name, doctor_name, email, mobile, appointment_date, appointment_time) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $data['patient_name'], $data['doctor_name'], $data['email'], $data['mobile'], $data['appointment_date'], $data['appointment_time']);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Appointment booked successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
    }
    $stmt->close();
}

function handleUpdate($conn, $data) {
    if (!validate($data)) {
        echo json_encode(["status" => "error", "message" => "Validation failed"]);
        return;
    }

    // Check for double booking excluding itself
    $stmt = $conn->prepare("SELECT id FROM appointments WHERE doctor_name = ? AND appointment_date = ? AND appointment_time = ? AND id != ? AND status != 'Cancelled'");
    $stmt->bind_param("sssi", $data['doctor_name'], $data['appointment_date'], $data['appointment_time'], $data['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "This time slot is already booked for " . $data['doctor_name']]);
        $stmt->close();
        return;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE appointments SET patient_name = ?, doctor_name = ?, email = ?, mobile = ?, appointment_date = ?, appointment_time = ? WHERE id = ?");
    $stmt->bind_param("ssssssi", $data['patient_name'], $data['doctor_name'], $data['email'], $data['mobile'], $data['appointment_date'], $data['appointment_time'], $data['id']);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Appointment updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
    }
    $stmt->close();
}

function handleStatusUpdate($conn, $data) {
    if (!isset($data['id']) || !isset($data['status'])) {
        echo json_encode(["status" => "error", "message" => "Missing data"]);
        return;
    }

    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $data['status'], $data['id']);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Status updated successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
    }
    $stmt->close();
}

function handleDelete($conn, $data) {
    if (!isset($data['id'])) {
        echo json_encode(["status" => "error", "message" => "ID required"]);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $data['id']);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Appointment deleted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
    }
    $stmt->close();
}

function validate($data) {
    if (empty($data['patient_name']) || empty($data['doctor_name']) || empty($data['email']) || empty($data['mobile']) || empty($data['appointment_date']) || empty($data['appointment_time'])) {
        return false;
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (!preg_match('/^[0-9]{10,15}$/', $data['mobile'])) {
        return false;
    }
    $today = date("Y-m-d");
    if ($data['appointment_date'] < $today) {
        return false;
    }

    // Time slot validation (09:00 - 18:00)
    $hour = (int)date("H", strtotime($data['appointment_time']));
    if ($hour < 9 || $hour >= 18) {
        return false;
    }

    return true;
}
?>