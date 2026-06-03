<?php
// api.php — Handles GET, POST, PUT, PATCH, DELETE for appointments

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

// Read raw JSON input (used by POST, PUT, PATCH, DELETE)
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {

    // ─────────────────────────────────────────
    // GET — Fetch all appointments
    // ─────────────────────────────────────────
    case 'GET':
        $sql    = "SELECT * FROM appointments ORDER BY appointment_date ASC, appointment_time ASC";
        $result = mysqli_query($conn, $sql);

        if (!$result) {
            respond(false, 'Database error: ' . mysqli_error($conn), null, 500);
        }

        $appointments = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $appointments[] = $row;
        }

        respond(true, 'Appointments fetched successfully', $appointments, 200);
        break;

    // ─────────────────────────────────────────
    // POST — Create a new appointment
    // ─────────────────────────────────────────
    case 'POST':
        $errors = validateInput($input);
        if (!empty($errors)) {
            respond(false, implode(' | ', $errors), null, 422);
        }

        $name   = trim($input['patient_name']);
        $email  = trim($input['email']);
        $mobile = trim($input['mobile']);
        $date   = trim($input['appointment_date']);
        $time   = trim($input['appointment_time']);
        $status = 'Pending';

        $stmt = mysqli_prepare($conn,
            "INSERT INTO appointments (patient_name, email, mobile, appointment_date, appointment_time, status)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            respond(false, 'Prepare error: ' . mysqli_error($conn), null, 500);
        }

        mysqli_stmt_bind_param($stmt, 'ssssss', $name, $email, $mobile, $date, $time, $status);

        if (mysqli_stmt_execute($stmt)) {
            $newId = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            // FIX: return 200 (not 201) so fetch wrapper does not throw
            respond(true, 'Appointment created successfully', ['id' => $newId], 200);
        } else {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            respond(false, 'Execute error: ' . $err, null, 500);
        }
        break;

    // ─────────────────────────────────────────
    // PUT — Update an existing appointment
    // ─────────────────────────────────────────
    case 'PUT':
        if (empty($input['id']) || !is_numeric($input['id'])) {
            respond(false, 'Invalid input: Appointment ID is required', null, 400);
        }

        $errors = validateInput($input);
        if (!empty($errors)) {
            respond(false, implode(' | ', $errors), null, 422);
        }

        $id     = (int) $input['id'];
        $name   = trim($input['patient_name']);
        $email  = trim($input['email']);
        $mobile = trim($input['mobile']);
        $date   = trim($input['appointment_date']);
        $time   = trim($input['appointment_time']);
        // Accept status sent from JS; default to Pending
        $allowed = ['Pending', 'Confirmed', 'Cancelled'];
        $status  = (isset($input['status']) && in_array($input['status'], $allowed))
                   ? $input['status'] : 'Pending';

        if (!appointmentExists($conn, $id)) {
            respond(false, 'Appointment not found', null, 404);
        }

        $stmt = mysqli_prepare($conn,
            "UPDATE appointments
             SET patient_name=?, email=?, mobile=?, appointment_date=?, appointment_time=?, status=?
             WHERE id=?"
        );

        if (!$stmt) {
            respond(false, 'Prepare error: ' . mysqli_error($conn), null, 500);
        }

        mysqli_stmt_bind_param($stmt, 'ssssssi', $name, $email, $mobile, $date, $time, $status, $id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            respond(true, 'Appointment updated successfully', null, 200);
        } else {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            respond(false, 'Execute error: ' . $err, null, 500);
        }
        break;

    // ─────────────────────────────────────────
    // PATCH — Update status only
    // ─────────────────────────────────────────
    case 'PATCH':
        if (empty($input['id']) || !is_numeric($input['id'])) {
            respond(false, 'Invalid input: Appointment ID is required', null, 400);
        }

        $allowedStatuses = ['Pending', 'Confirmed', 'Cancelled'];
        if (empty($input['status']) || !in_array($input['status'], $allowedStatuses)) {
            respond(false, 'Invalid input: Status must be Pending, Confirmed, or Cancelled', null, 422);
        }

        $id     = (int) $input['id'];
        $status = $input['status'];

        if (!appointmentExists($conn, $id)) {
            respond(false, 'Appointment not found', null, 404);
        }

        $stmt = mysqli_prepare($conn, "UPDATE appointments SET status=? WHERE id=?");

        if (!$stmt) {
            respond(false, 'Prepare error: ' . mysqli_error($conn), null, 500);
        }

        mysqli_stmt_bind_param($stmt, 'si', $status, $id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            respond(true, 'Status updated successfully', null, 200);
        } else {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            respond(false, 'Execute error: ' . $err, null, 500);
        }
        break;

    // ─────────────────────────────────────────
    // DELETE — Remove an appointment
    // ─────────────────────────────────────────
    case 'DELETE':
        if (empty($input['id']) || !is_numeric($input['id'])) {
            respond(false, 'Invalid input: Appointment ID is required', null, 400);
        }

        $id = (int) $input['id'];

        if (!appointmentExists($conn, $id)) {
            respond(false, 'Appointment not found', null, 404);
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM appointments WHERE id=?");

        if (!$stmt) {
            respond(false, 'Prepare error: ' . mysqli_error($conn), null, 500);
        }

        mysqli_stmt_bind_param($stmt, 'i', $id);

        if (mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            respond(true, 'Appointment deleted successfully', null, 200);
        } else {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            respond(false, 'Execute error: ' . $err, null, 500);
        }
        break;

    default:
        respond(false, 'Method not allowed', null, 405);
}

mysqli_close($conn);

// ─────────────────────────────────────────
// Helper Functions
// ─────────────────────────────────────────

function respond(bool $success, string $message, $data = null, int $code = 200): void {
    http_response_code($code);
    $response = ['success' => $success, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

function appointmentExists($conn, int $id): bool {
    $stmt = mysqli_prepare($conn, "SELECT id FROM appointments WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

function validateInput(?array $data): array {
    $errors = [];

    if (empty($data)) {
        return ['Invalid input: No data received'];
    }

    $required = ['patient_name', 'email', 'mobile', 'appointment_date', 'appointment_time'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $label    = ucwords(str_replace('_', ' ', $field));
            $errors[] = "$label is required";
        }
    }

    if (!empty($errors)) return $errors;

    if (!preg_match('/^[a-zA-Z\s]{2,100}$/', trim($data['patient_name']))) {
        $errors[] = 'Patient name must be 2–100 alphabetic characters';
    }

    if (!filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    if (!preg_match('/^\+?[0-9]{10,15}$/', trim($data['mobile']))) {
        $errors[] = 'Mobile number must be 10–15 digits';
    }

    $today    = new DateTime('today');
    $apptDate = DateTime::createFromFormat('Y-m-d', trim($data['appointment_date']));
    if (!$apptDate || $apptDate < $today) {
        $errors[] = 'Appointment date cannot be a past date';
    }

    return $errors;
}
?>