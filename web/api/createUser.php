<?php
/**
 * Endpoint: POST /api/createUser.php
 * Super Admin only
 */

header('Content-Type: application/json; charset=utf-8');

define('CASHCUE_APP', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

if (!isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$isSA = (int)($_POST['newSuperAdmin'] ?? 0);
$isActive = isset($_POST['is_active']) ? 1 : 0;

if (!$username || !$email || !$password) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

try {

    $db  = new Database('production');
    $pdo = $db->getConnection();

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO user (username, email, password_hash, is_super_admin, is_active)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([$username, $email, $passwordHash, $isSA, $isActive]);

    echo json_encode(['status' => 'success', 'message' => 'User created successfully']);

} catch (PDOException $e) {

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Username or email already exists']);
}