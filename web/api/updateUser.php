<?php
/**
 * Endpoint: POST /api/updateUser.php
 */

header('Content-Type: application/json; charset=utf-8');
define('CASHCUE_APP', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$id    = (int)($_POST['id'] ?? 0);
$email = trim($_POST['email'] ?? '');
$isSA  = isset($_POST['is_super_admin']) ? 1 : 0;

if (!$id || !$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

try {

    $db  = new Database('production');
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        UPDATE user
        SET email = ?, is_super_admin = ?
        WHERE id = ?
    ");

    $stmt->execute([$email, $isSA, $id]);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode(['error' => 'Update failed']);
}