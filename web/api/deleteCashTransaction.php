<?php
/**
 * -----------------------------------------------
 * API endpoint to delete a cash transaction by ID.
 * Method: POST
 * URL: /api/deleteCashTransaction.php
 * Body: { "id": 123 }
 * Authentication: Required (must be logged in)
 * Response:
 * - Success: { "success": true }
 * - Error: { "success": false, "error": "Error message" }
 * The endpoint validates the input ID, checks if the cash transaction exists, deletes it from the database,
 * recalculates the cash balance for the associated broker account, updates the cash account balance, and returns a JSON response indicating success or failure.
 * ------------------------------------------------------------
 * Notes:
 * - The endpoint uses transactions to ensure data integrity, rolling back if any step fails.
 * - The endpoint assumes that the cash account balance is derived from summing all cash transactions, so it does not directly update the balance but relies on the cash transaction record to reflect the deletion.    
 * - The endpoint returns a generic error message on failure, but in a production environment, you might want to log the detailed error message to a file or monitoring system instead of returning it in the response, to avoid exposing sensitive information about your database structure or application logic to potential attackers.
 * ------------------------------------------------------------
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
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid HTTP method');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        throw new Exception('Missing cash transaction id');
    }

    $id = (int)$input['id'];

    $db  = new Database();
    $pdo = $db->getConnection();

    // --------------------------------------------------
    // Start transaction (cash consistency is critical)
    // --------------------------------------------------
    $pdo->beginTransaction();

    // --------------------------------------------------
    // Fetch transaction to ensure it exists
    // --------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT broker_account_id, type
        FROM cash_transaction
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Cash transaction not found');
    }

    $brokerAccountId = (int) $transaction['broker_account_id'];

    // --------------------------------------------------
    // Delete cash transaction
    // --------------------------------------------------
    $delStmt = $pdo->prepare("
        DELETE FROM cash_transaction
        WHERE id = :id
    ");
    $delStmt->execute([':id' => $id]);

    // --------------------------------------------------
    // Recalculate cash balance
    // --------------------------------------------------
    $sumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS balance
        FROM cash_transaction
        WHERE broker_account_id = :broker_account_id
    ");
    $sumStmt->execute([':broker_account_id' => $brokerAccountId]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);

    $balance = (float) $sumRow['balance'];

    // --------------------------------------------------
    // Update cash account balance
    // --------------------------------------------------
    $updStmt = $pdo->prepare("
        UPDATE cash_account
        SET current_balance = :balance,
            updated_at = NOW()
        WHERE broker_account_id = :broker_account_id
    ");
    $updStmt->execute([
        ':balance'            => $balance,
        ':broker_account_id'  => $brokerAccountId
    ]);

    // --------------------------------------------------
    // Commit
    // --------------------------------------------------
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
