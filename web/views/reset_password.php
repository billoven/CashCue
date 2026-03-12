<?php
/**
 * CashCue - Reset Password Page
 * ------------------------------------------------------------
 * This page handles the password reset process when a user
 * clicks the "forgot password" link received by email.
 *
 * Responsibilities:
 *   - Validate the reset token from the email link
 *   - Ensure token is not expired
 *   - Display a form to enter new password
 *   - Update the user's password and clear the token
 *   - Provide feedback messages to the user
 *
 * Security notes:
 *   - Only valid tokens with non-expired timestamps are accepted
 *   - Passwords are hashed with password_hash()
 *   - Token is invalidated immediately after successful reset
 *
 * Author: Pierre
 * Date: 2026-03-11
 */

declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/database.php';

$error = null;
$message = null;
$user  = null;

// Retrieve token from URL query string
$token = $_GET['token'] ?? '';

if (!$token) {
    $error = "Invalid token.";
} else {
    try {
        $db  = new Database();
        $pdo = $db->getConnection();

        // Check if token exists and is not expired
        $stmt = $pdo->prepare("
            SELECT id, username
            FROM user
            WHERE reset_token = :token
              AND reset_token_expiry > NOW()
            LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "Token invalid or expired.";
        }

    } catch (Throwable $e) {
        error_log("Reset password error: " . $e->getMessage());
        $error = "System error. Please try again later.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (!$new_password || $new_password !== $confirm_password) {
        $error = "Passwords do not match or are empty.";
    } else {
        try {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password and invalidate token
            $stmt = $pdo->prepare("
                UPDATE user
                SET password_hash = :hash,
                    reset_token = NULL,
                    reset_token_expiry = NULL
                WHERE id = :id
            ");
            $stmt->execute([
                ':hash' => $hash,
                ':id'   => $user['id']
            ]);

            $message = "Password successfully reset. You can now log in.";
            $user = null; // Invalidate token for display purposes

        } catch (Throwable $e) {
            error_log("Password update error: " . $e->getMessage());
            $error = "System error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CashCue - Reset Password</title>
<link href="/cashcue/assets/css/style.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
</head>
<body class="bg-light">

<div class="d-flex justify-content-center align-items-center" style="min-height:100vh;">
    <div class="card shadow-lg p-5" style="width:400px;">
        <h3 class="text-center mb-4 text-primary fw-bold">
            Reset Your CashCue Password
        </h3>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($message): ?>
            <div class="alert alert-success" role="alert">
                <?= htmlspecialchars($message) ?>
            </div>
            <div class="text-center mt-3">
                <a href="/cashcue/views/login.php" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Login
                </a>
            </div>
        <?php endif; ?>

        <?php if ($user): ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <input type="password"
                       name="new_password"
                       class="form-control form-control-lg"
                       placeholder="New Password"
                       required
                       minlength="8">
            </div>

            <div class="mb-4">
                <input type="password"
                       name="confirm_password"
                       class="form-control form-control-lg"
                       placeholder="Confirm Password"
                       required
                       minlength="8">
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-key-fill me-2"></i> Reset Password
            </button>
        </form>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>