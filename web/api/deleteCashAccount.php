<?php
/**
 * API endpoint to delete a cash account by ID.
 * Method: DELETE
 * URL: /api/deleteCashAccount.php?id={cash_account_id}
 *
 * Authentication: Required (must be logged in)
 *
 * Response:
 * - Success: { "success": true }
 * - Error: { "success": false, "error": "Error message" }
 *
 * The endpoint validates the input ID, checks if the cash account exists and belongs to the authenticated user, and then deletes it from the database. It returns a JSON response indicating success or failure.
 */ 
header('Content-Type: application/json; charset=utf-8');

// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../includes/auth.php';

// include database connection class
require_once __DIR__ . '/../config/database.php';

try {
    if (!isset($_GET['id'])) throw new Exception('Missing id');

    $id = intval($_GET['id']);
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("DELETE FROM cash_account WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
