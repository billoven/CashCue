<?php
/**
 * CashCue - Forgot Password Page
 * ------------------------------------------------------------
 * Allows a user to request a password reset link.
 *
 * Workflow:
 *   1. User enters email address
 *   2. System generates a secure reset token
 *   3. Token and expiration time are stored in the user table
 *   4. Reset link is emailed to the user
 *
 * Security measures:
 *   - Token generated with random_bytes()
 *   - Token expires after 1 hour
 *   - Email existence is not revealed to the user
 *
 * Author: Pierre
 * Date: 2026-03-11
 */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../config/database.php';

$message = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $error = "Please enter your email address.";
    } else {

        try {

            $db  = new Database();
            $pdo = $db->getConnection();
            $config = $db->getAppConfig();

            // ------------------------------------------------
            // Find user
            // ------------------------------------------------
            $stmt = $pdo->prepare("
                SELECT id, email
                FROM user
                WHERE email = :email
                LIMIT 1
            ");

            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user) {

                // ------------------------------------------------
                // Generate secure token
                // ------------------------------------------------
                $token = bin2hex(random_bytes(32));

                // Store token and set expiry to +1 hour using MySQL time
                $stmt = $pdo->prepare("
                    UPDATE user
                    SET reset_token = :token,
                        reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR)
                    WHERE id = :id
                ");

                $stmt->execute([
                    ':token' => $token,
                    ':id'    => $user['id']
                ]);

                // ------------------------------------------------
                // Build reset URL dynamically
                // ------------------------------------------------
                $host = $config['APP_HOST'] ?? 'localhost';
                $port = $config['APP_PORT'] ?? 80;

                $protocol = ($port == 443) ? 'https' : 'http';

                $reset_link = sprintf(
                    "%s://%s:%d/cashcue/views/reset_password.php?token=%s",
                    $protocol,
                    $host,
                    $port,
                    $token
                );

                // ------------------------------------------------
                // Send email
                // ------------------------------------------------
                $subject = "CashCue Password Reset";

                $body = "
                <html>
                <body style='font-family:Arial,sans-serif'>

                <h2>CashCue Password Reset</h2>

                <p>A request was made to reset your password.</p>
                <p>
                <a href='$reset_link'
                style='padding:10px 20px;background:#2c7be5;color:white;text-decoration:none;border-radius:5px;'>
                Reset Password
                </a>
                </p>

                <p>This link expires in 1 hour.</p>

                <p>If you did not request this reset, you can ignore this email.</p>

                <hr>
                <small>CashCue Portfolio Manager</small>

                </body>
                </html>
                ";

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8\r\n";

                mail($email, $subject, $body, $headers);
            }

            // ------------------------------------------------
            // Always show same message (security)
            // ------------------------------------------------
            $message = "If the email exists, a password reset link has been sent.";

        } catch (Throwable $e) {

            error_log("Forgot password error: " . $e->getMessage());
            $error = "System error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>CashCue - Forgot Password</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="d-flex justify-content-center align-items-center" style="min-height:100vh;">

<div class="card shadow-lg p-5" style="width:420px;">

<h3 class="text-center mb-4 text-primary fw-bold">
CashCue Password Reset
</h3>

<?php if ($error): ?>
<div class="alert alert-danger">
<?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($message): ?>
<div class="alert alert-success">
<?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<form method="post">

<div class="mb-3">
<input
type="email"
name="email"
class="form-control form-control-lg"
placeholder="Enter your email"
required>
</div>

<button class="btn btn-primary w-100 btn-lg">
Send Reset Link
</button>

</form>

<div class="text-center mt-3">

<a href="/cashcue/views/login.php">
Back to Login
</a>

</div>

</div>

</div>

</body>
</html>