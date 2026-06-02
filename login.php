<?php
require_once 'config.php';
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Ajax Healthcare</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-8 col-md-6 col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-dark mb-1">Welcome Back</h2>
                            <p class="text-muted small">Please enter your details to login</p>
                        </div>

                        <div id="loginAlert" class="alert alert-danger rounded-3 small mb-4" style="display: none;"></div>

                        <form id="loginForm">
                            <div class="mb-3">
                                <label for="username" class="form-label fw-medium small">Username</label>
                                <input type="text" class="form-control form-control-lg border-2 bg-light rounded-3 fs-6" id="username" placeholder="Enter username" required>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label fw-medium small">Password</label>
                                <input type="password" class="form-control form-control-lg border-2 bg-light rounded-3 fs-6" id="password" placeholder="••••••••" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold rounded-3 py-3 mt-2 shadow-sm d-flex align-items-center justify-content-center transition-all">
                                <span>Login</span>
                                <div class="spinner-border spinner-border-sm ms-2" id="loader" role="status" style="display: none;">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="text-center mt-4 text-muted small">
                    &copy; 2026 Ajax Healthcare. All rights reserved.
                </div>
            </div>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html>