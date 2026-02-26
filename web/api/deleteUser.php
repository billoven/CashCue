<?php
header('Content-Type: application/json; charset=utf-8');
define('CASHCUE_APP', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Forbidden']);
    exit;
}

$id = $_POST['id'] ?? null;

if (!$id || !ctype_digit($id)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Invalid user ID']);
    exit;
}

try {

    $db  = new Database('production');
    $pdo = $db->getConnection();

    // Prevent self-deletion
    if ($id == $_SESSION['user_id']) {
        echo json_encode(['status'=>'error','message'=>'Cannot delete yourself']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM user WHERE id = :id");
    $stmt->execute([':id'=>$id]);

    echo json_encode([
        'status'=>'success',
        'message'=>'User deleted'
    ]);

} catch(Throwable $e) {

    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>'Delete failed']);
}