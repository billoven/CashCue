<?php

header('Content-Type: application/json; charset=utf-8');
define('CASHCUE_APP', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$userId = (int)($_POST['user_id'] ?? 0);

if (!$userId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user ID']);
    exit;
}

try {

    $db  = new Database('production');
    $pdo = $db->getConnection();

    // Récupérer état actuel
    $stmt = $pdo->prepare("SELECT is_super_admin FROM user WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    $newValue = $user['is_super_admin'] ? 0 : 1;

    // 🔒 Sécurité : empêcher suppression du dernier super admin
    if ($user['is_super_admin']) {

        $countStmt = $pdo->query("SELECT COUNT(*) FROM user WHERE is_super_admin = 1");
        $count = $countStmt->fetchColumn();

        if ($count <= 1) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Cannot remove the last SuperAdmin'
            ]);
            exit;
        }
    }

    $update = $pdo->prepare("UPDATE user SET is_super_admin = ? WHERE id = ?");
    $update->execute([$newValue, $userId]);

    echo json_encode([
        'status' => 'success',
        'new_value' => $newValue
    ]);

} catch (Exception $e) {

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}