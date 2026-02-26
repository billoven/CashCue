<?php
/**
 * API endpoint to update a cash transaction
 * 
 * Expects JSON input with at least the 'id' of the transaction to update, and any of the following optional fields:
 * - date
 * - amount
 * - type
 * - reference_id
 * - comment
 * 
 * Returns JSON response:
 * {
 *   success: true|false,
 *   error: "error message if any"
 * }
 * 
 * Notes:
 * - Only provided fields will be updated (partial update)
 * - If the transaction is linked to a cash account, the cash account's current_balance will be recalculated after the update
 * - Input validation is minimal; it's assumed that the client sends valid data types (e.g., date as string, amount as number)
 * - The 'type' field can be used to categorize transactions (e.g., DEPOSIT, WITHDRAWAL, DIVIDEND, FEES, ADJUSTMENT), but no specific rules
 *  are enforced in this endpoint regarding the type or amount values. It's up to the client to ensure data consistency.
 * - The endpoint requires authentication, and the user must have permission to update the specified transaction.
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
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'])) throw new Exception('Missing id');

    $fields = [];
    $params = [':id' => $input['id']];

    if (isset($input['date'])) { $fields[] = 'date = :date'; $params[':date'] = $input['date']; }
    if (isset($input['amount'])) { $fields[] = 'amount = :amount'; $params[':amount'] = $input['amount']; }
    if (isset($input['type'])) { $fields[] = 'type = :type'; $params[':type'] = $input['type']; }
    if (array_key_exists('reference_id', $input)) { $fields[] = 'reference_id = :reference_id'; $params[':reference_id'] = $input['reference_id']; }
    if (array_key_exists('comment', $input)) { $fields[] = 'comment = :comment'; $params[':comment'] = $input['comment']; }

    if (empty($fields)) throw new Exception('No fields to update');

    $sql = "UPDATE cash_transaction SET " . implode(', ', $fields) . " WHERE id = :id";
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // update cash_account.current_balance if exists
    $tx = $pdo->prepare("SELECT broker_account_id FROM cash_transaction WHERE id = :id");
    $tx->execute([':id' => $input['id']]);
    $txr = $tx->fetch(PDO::FETCH_ASSOC);
    if ($txr) {
        $broker_account_id = $txr['broker_account_id'];
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_account_id");
        $sumStmt->execute([':broker_account_id' => $broker_account_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        $upd = $pdo->prepare("UPDATE cash_account SET current_balance = :bal, updated_at = NOW() WHERE broker_account_id = :broker_account_id");
        $upd->execute([':bal' => $sumRow['sum_amount'], ':broker_account_id' => $broker_account_id]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
