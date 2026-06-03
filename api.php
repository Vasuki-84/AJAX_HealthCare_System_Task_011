<?php
header("Content-Type: application/json");
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Auto-update database schema if needed
try {
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'appointments'");
    if ($result->num_rows == 0) {
        // Create table if missing
        $conn->query("CREATE TABLE appointments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            patient_name VARCHAR(100) NOT NULL,
            doctor_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            mobile VARCHAR(20) NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            status ENUM('Pending', 'Confirmed', 'Cancelled') DEFAULT 'Pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } else {
        // Check for missing doctor_name column
        $conn->query("SELECT doctor_name FROM appointments LIMIT 1");
    }
} catch (Exception $e) {
    if (strpos($e->getMessage(), "Unknown column 'doctor_name'") !== false) {
        $conn->query("ALTER TABLE appointments ADD COLUMN doctor_name VARCHAR(100) AFTER patient_name");
    }
}


// API handles patient CRUD operations
try {
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
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "API Error: " . $e->getMessage()]);
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
    $validationResult = validate($data);
    if ($validationResult !== true) {
        echo json_encode(["status" => "error", "message" => $validationResult]);
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
        echo json_encode([
            "status" => "success", 
            "message" => "Appointment booked successfully",
            "id" => $stmt->insert_id
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
    }
    $stmt->close();
}

function handleUpdate($conn, $data) {
    $validationResult = validate($data);
    if ($validationResult !== true) {
        echo json_encode(["status" => "error", "message" => $validationResult]);
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
    if (empty($data['patient_name'])) return "Patient name is required";
    if (empty($data['doctor_name'])) return "Doctor name is required";
    if (empty($data['email'])) return "Email is required";
    if (empty($data['mobile'])) return "Mobile number is required";
    if (empty($data['appointment_date'])) return "Appointment date is required";
    if (empty($data['appointment_time'])) return "Appointment time is required";


    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format";
    }


    if (!preg_match('/^[0-9]{10}$/', $data['mobile'])) {
        return "Mobile number must contain exactly 10 digits";
    }

      if ($data['appointment_date'] < date('Y-m-d')) {
        return "Appointment date cannot be in the past";
    }


    // Accept 12-hour format with AM/PM, e.g., "02:30 PM"
    $timeObj = DateTime::createFromFormat('h:i A', $data['appointment_time']);
    if ($timeObj === false) {
        // Fallback to 24-hour format
        $timeObj = DateTime::createFromFormat('H:i', $data['appointment_time']);
        if ($timeObj === false) {
            return "Invalid appointment time format. Use HH:MM (24h) or HH:MM AM/PM (12h).";
        }
    }
    $hour = (int) $timeObj->format('H');
    if ($hour < 9 || $hour >= 18) {
        return "Appointment time must be between 09:00 and 18:00";
    }


    return true;
}