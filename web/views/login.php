<?php
/**
 * CashCue - Login Page
 * ------------------------------------------------------------
 * This page handles user authentication for CashCue.
 * Responsibilities:
 *   - Display login form with proper DOCTYPE and Bootstrap styling
 *   - Validate username/password against the database
 *   - Set session variables for user info and session timeout
 *   - Redirect authenticated users to dashboard
 * ------------------------------------------------------------
 * Author: Pierre
 * Date: 2026-02-23
 */

declare(strict_types=1);

// ----------------------------------------------------------------
// Start PHP session (necessary to track logged-in state)
// ----------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------------
// Redirect to dashboard if already authenticated
// ----------------------------------------------------------------
if (isset($_SESSION['user_id'])) {
    header("Location: /cashcue/index.php");
    exit;
}

// ----------------------------------------------------------------
// Include database connection
// ----------------------------------------------------------------
require_once __DIR__ . '/../config/database.php';

$login_error = null;

// ----------------------------------------------------------------
// Handle form submission
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $login_error = "Please fill in all fields.";
    } else {
        try {
            $db  = new Database();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("
                SELECT id, username, password_hash, is_super_admin
                FROM user
                WHERE username = :username
                LIMIT 1
            ");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password
            if ($user && password_verify($password, $user['password_hash'])) {
                // Successful login
                session_regenerate_id(true); // Prevent session fixation

                $_SESSION['user_id']        = (int)$user['id'];
                $_SESSION['username']       = $user['username'];
                $_SESSION['is_super_admin'] = (bool)$user['is_super_admin'];

                // Session timeout tracking
                $_SESSION['LAST_ACTIVITY']  = time();
                $_SESSION['SESSION_DURATION'] = 1800; // 30 minutes

                header("Location: /cashcue/index.php");
                exit;
            } else {
                $login_error = "Invalid credentials. Please try again.";
            }

        } catch (Throwable $e) {
            error_log("Login error: " . $e->getMessage());
            $login_error = "System error. Please try again later.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CashCue Login</title>
<link href="/cashcue/web/assets/css/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="d-flex justify-content-center align-items-center" style="min-height:100vh;">
    <div class="card shadow-lg p-5" style="width:400px;">
        <h3 class="text-center mb-4 text-primary fw-bold">
            CashCue Access
        </h3>

        <?php if ($login_error): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($login_error) ?>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="mb-3">
                <input type="text"
                       name="username"
                       class="form-control form-control-lg"
                       placeholder="Username"
                       required>
            </div>

            <div class="mb-4">
                <input type="password"
                       name="password"
                       class="form-control form-control-lg"
                       placeholder="Password"
                       required>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-box-arrow-in-right me-2"></i> Login
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>