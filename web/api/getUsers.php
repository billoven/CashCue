<?php
/**
 * ------------------------------------------------------------
 * Endpoint: GET /api/getUsers.php
 * ------------------------------------------------------------
 * Description:
 *   Returns all users (Super Admin only)
 * ------------------------------------------------------------
 */

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

try {

    $db  = new Database('production');
    $pdo = $db->getConnection();

    $stmt = $pdo->query("
        SELECT 
            id,
            username,
            email,
            is_super_admin,
            is_active,
            created_at,
            updated_at
        FROM user
        ORDER BY username
    ");

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------------
    // Standardized success response
    // --------------------------------------------------------
    echo json_encode([
        'status' => 'success',
        'data'   => $users
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'status'  => 'error',
        'message' => 'Unable to retrieve users'
    ]);
}