<?php
/**
 * CashCue - Personal Finance and Investment Tracker
 * API Endpoint: Get Cash Balance for a Broker Account
 * 
 * This endpoint retrieves the current cash balance for a specified broker account. It first checks if there is a current_balance in the cash_account table for the given broker_account_id. If it exists, it returns that value. If not, it calculates the balance by summing all amounts from the cash_transaction table for that broker account.
 * 
 * Request:
 * GET /api/getCashBalance.php?broker_account_id=123
 * 
 * Response:
 * {
 *   "broker_account_id": 123,
 *   "balance": 1000.50
 * }
 * 
 * Error Response (e.g., missing parameter):
 * {
 *   "error": "Missing broker_account_id"
 * }
 * Notes:
 * - The endpoint requires authentication, so the user must be logged in to access it.
 * - The 'broker_account_id' parameter is required and must be a valid integer corresponding to an existing broker account.
 * - The endpoint uses prepared statements to prevent SQL injection and ensure secure database access.
 * - The balance is returned as a floating-point number, which represents the total cash balance
 * for the specified broker account. If there are no transactions, the balance will be returned as 0.00.
 * - The endpoint assumes that the cash account balance is derived from summing all cash transactions, so it does not directly update the balance but relies on the cash transaction records to reflect the current state
 * of the cash balance for the broker account. In a production application, you might want to implement caching or a more efficient way to track the current balance if performance becomes an issue with a large number of transactions.
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
    if (!isset($_GET['broker_account_id'])) throw new Exception('Missing broker_account_id');

    $broker_account_id = intval($_GET['broker_account_id']);
    $db = new Database();
    $pdo = $db->getConnection();

    // Prefer current_balance from cash_account if exist
    $stmt = $pdo->prepare("SELECT current_balance FROM cash_account WHERE broker_account_id = :broker_account_id LIMIT 1");
    $stmt->execute([':broker_account_id' => $broker_account_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $balance = (float)$row['current_balance'];
    } else {
        // compute from transactions
        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_account_id");
        $s->execute([':broker_account_id' => $broker_account_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $balance = $r ? (float)$r['sum_amount'] : 0.00;
    }

    echo json_encode(['broker_account_id' => $broker_account_id, 'balance' => $balance]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
