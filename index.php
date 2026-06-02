<?php
require_once 'config.php';
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajax Healthcare - Patient Appointment Management</title>
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Patient Appointment Form</h4>
                    </div>
                    <div class="card-body">
                        <form id="appointmentForm">
                            <input type="hidden" id="appointmentId" name="id">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="patientName" class="form-label">Patient Name</label>
                                    <input type="text" class="form-control" id="patientName" name="patient_name" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="doctorName" class="form-label">Select Doctor</label>
                                    <select class="form-select" id="doctorName" name="doctor_name" required>
                                        <option value="" disabled selected>Choose a doctor</option>
                                        <option value="Dr. Smith">Dr. Smith (General Physician)</option>
                                        <option value="Dr. Johnson">Dr. Johnson (Cardiologist)</option>
                                        <option value="Dr. Williams">Dr. Williams (Pediatrician)</option>
                                        <option value="Dr. Brown">Dr. Brown (Dermatologist)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="mobile" class="form-label">Mobile</label>
                                    <input type="text" class="form-control" id="mobile" name="mobile" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="appointmentDate" class="form-label">Appointment Date</label>
                                    <input type="date" class="form-control" id="appointmentDate" name="appointment_date" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="appointmentTime" class="form-label">Appointment Time</label>
                                    <input type="time" class="form-control" id="appointmentTime" name="appointment_time" required>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" id="submitBtn" class="btn btn-primary px-4">Book Appointment</button>
                                    <button type="button" id="cancelEditBtn" class="btn btn-secondary px-4 d-none">Cancel Edit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Appointment List</h4>
                        <div>
                            <button id="refreshBtn" class="btn btn-sm btn-outline-light me-2">Refresh</button>
                            <button id="logoutBtn" class="btn btn-sm btn-danger">Logout</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Patient Name</th>
                                        <th>Doctor</th>
                                        <th>Email</th>
                                        <th>Mobile</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="appointmentTableBody">
                                    <!-- Appointments will be loaded here via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="app.js"></script>
</body>
</html>