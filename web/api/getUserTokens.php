<?php
header('Content-Type: application/json; charset=utf-8');

define('CASHCUE_APP', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// ------------------------------------------------------------
// Security: Super Admin only
// ------------------------------------------------------------
if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Forbidden'
    ]);
    exit;
}

// ------------------------------------------------------------
// Validate user_id
// ------------------------------------------------------------
$userId = $_GET['user_id'] ?? null;

if (!$userId || !ctype_digit($userId)) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Invalid user_id'
    ]);
    exit;
}

try {

    $db  = new Database('production');
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        SELECT 
            t.id,
            t.name,
            t.expires_at,
            t.last_used_at,
            t.is_revoked,
            t.created_at
        FROM user_api_token t
        WHERE t.user_id = :user_id
        ORDER BY t.created_at DESC
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data'   => $tokens
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'status'  => 'error',
        'message' => 'Unable to retrieve tokens'
    ]);
}