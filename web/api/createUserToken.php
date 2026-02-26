<?php
header('Content-Type: application/json; charset=utf-8');
define('CASHCUE_APP', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Restrict access to Super Admins
if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

// Retrieve POST parameters
$userId  = (int)($_POST['user_id'] ?? 0);
$name    = trim($_POST['name'] ?? '');
$expires = $_POST['expires_at'] ?? null;

if (!$userId || !$name) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

try {
    $db  = new Database('production');
    $pdo = $db->getConnection();

    // -----------------------------
    // Check for unique token name
    // -----------------------------
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM user_api_token 
        WHERE user_id = ? AND name = ?
    ");
    $check->execute([$userId, $name]);
    $count = (int)$check->fetchColumn();

    if ($count > 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Token name already exists for this user'
        ]);
        exit;
    }

    // -----------------------------
    // Generate raw token and hash
    // -----------------------------
    $rawToken  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    // -----------------------------
    // Insert token into database
    // -----------------------------
    $stmt = $pdo->prepare("
        INSERT INTO user_api_token (user_id, name, token_hash, expires_at, last_used_at, is_revoked)
        VALUES (?, ?, ?, ?, NULL, 0)
    ");
    $stmt->execute([$userId, $name, $tokenHash, $expires]);

    // Return the raw token to front-end
    echo json_encode([
        'status' => 'success',
        'token'  => $rawToken
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Token creation failed'
    ]);
}