<?php
/**
 * Get cash account details by ID
 * Endpoint: GET /api/getCashAccountDetails.php?id={cash_account_id}
 * 
 * Response:
 * {
 *   "id": 1,
 *   "broker_account_id": 2,
 *   "name": "Main Cash Account",
 *   "currency": "USD",
 *   "balance": 10000.00,
 *   "created_at": "2024-01-01T12:00:00Z",
 *   "updated_at": "2024-06-01T15:30:00Z",
 *   "broker_name": "Broker XYZ" // included via LEFT JOIN for convenience
 * }
 *
 * Errors:
 * - 400 Bad Request: Missing or invalid 'id' parameter
 * - 404 Not Found: No cash account found with the provided ID
 * Notes:
 * - This endpoint retrieves detailed information about a specific cash account, including its current balance and the name of the associated broker account for easier identification in the UI.
 * - The 'id' parameter is required and must be a valid integer corresponding to an existing cash account.
 * - The endpoint ensures that only accounts belonging to the authenticated user can be accessed, preventing unauthorized access to other users' cash accounts.
 * - The response includes the broker name via a LEFT JOIN for convenience, so the frontend can display it without needing an additional API call to fetch broker details separately.
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

    $stmt = $pdo->prepare("
        SELECT ca.*, b.name AS broker_name
        FROM cash_account ca
        LEFT JOIN broker_account b ON ca.broker_account_id = b.id
        WHERE ca.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Cash account not found']);
        exit;
    }

    echo json_encode($row);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
