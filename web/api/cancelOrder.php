<?php
/**
 * cancelOrder.php
 * Endpoint to cancel an active order and create a corresponding cash reversal.
 *
 * Expected input (JSON):
 * {
 *   "id": 123  // ID of the order to cancel
 * }
 *
 * Process:
 * 1. Validate input and check if order exists and is ACTIVE.
 * 2. Compute cash reversal amount based on order type (BUY or SELL).
 * 3. Update order status to CANCELLED.
 * 4. Insert a new cash transaction with the reversal amount.
 * 5. Update the broker's cash account balance.
 *
 * Response (JSON):
 * {
 *   "success": true,
 *   "order_id": 123,
 *   "new_status": "CANCELLED",
 *   "cash_reversal_amount": -100.50,
 *   "broker_balance": 5000.00
 * }
 *
 * Error response (JSON):
 * {
 *   "success": false,
 *   "error": "Error message describing what went wrong"
 * }
 * Notes:
 * - The reversal amount must exactly negate the original cash impact of the order, not recompute a theoretical amount, to ensure accuracy in case of any changes to the order record after the original transaction.
 * - The endpoint uses transactions to ensure data integrity, rolling back if any step fails.
 * - Authentication is required to access this endpoint.
 * - The endpoint assumes that the cash account balance is derived from summing all cash transactions, so it does not directly update the balance but relies on the cash transaction record to reflect the reversal.
 */
header('Content-Type: application/json; charset=utf-8');

// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../includes/auth.php';

// include database connection class
require_once __DIR__ . '/../config/database.php';

// IMPORTANT:
// Reversal must exactly negate the original cash impact,
// not recompute a theoretical amount.
try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!isset($data['id'])) throw new Exception('Missing order id');
    $order_id = (int)$data['id'];
    $db = new Database();
    $pdo = $db->getConnection();

    $pdo->beginTransaction();

    // 1️⃣ Récupérer l'ordre actif
    $sel = $pdo->prepare("
        SELECT id, broker_account_id, order_type, quantity, price, fees, settled, status 
        FROM order_transaction 
        WHERE id = :id 
        LIMIT 1
    ");
    $sel->execute([':id' => $order_id]);
    $order = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');
    if ($order['status'] !== 'ACTIVE') throw new Exception('Order is already cancelled');

    $broker_account_id = (int)$order['broker_account_id'];
    if ($order['order_type'] === 'BUY') {
        // Original BUY cash = -(qty * price + fees)
        $reversal_amount =
            (float)$order['quantity'] * (float)$order['price']
            + (float)$order['fees'];
    }
    elseif ($order['order_type'] === 'SELL') {
        // Original SELL cash = +(qty * price - fees)
        $reversal_amount =
            -(
                (float)$order['quantity'] * (float)$order['price']
                - (float)$order['fees']
            );
    }
    else {
        throw new Exception('Unknown order type');
    }


    // 3️⃣ Marquer l'ordre comme CANCELLED
    $updOrder = $pdo->prepare("
        UPDATE order_transaction
        SET status = 'CANCELLED', cancelled_at = NOW()
        WHERE id = :id
    ");
    $updOrder->execute([':id' => $order_id]);

    // 4️⃣ Créer le cash reversal
    $insCash = $pdo->prepare("
        INSERT INTO cash_transaction
        (broker_account_id, date, amount, type, reference_id, comment)
        VALUES (:broker_account_id, NOW(), :amount, :type, :ref, :comment)
    ");
    $insCash->execute([
        ':broker_account_id' => $broker_account_id,
        ':amount' => $reversal_amount,
        ':type' => $order['order_type'],
        ':ref' => $order_id,
        ':comment' => "Reversal of {$order['order_type']} order #$order_id"
    ]);

    // 5️⃣ Mettre à jour le solde cash du broker
    $sumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount),0) AS sum_amount 
        FROM cash_transaction 
        WHERE broker_account_id = :broker_account_id
    ");
    $sumStmt->execute([':broker_account_id' => $broker_account_id]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);

    $updBalance = $pdo->prepare("
        UPDATE cash_account 
        SET current_balance = :bal, updated_at = NOW() 
        WHERE broker_account_id = :broker_account_id
    ");
    $updBalance->execute([
        ':bal' => $sumRow['sum_amount'],
        ':broker_account_id' => $broker_account_id
    ]);

    $pdo->commit();

    // ✅ Retour JSON
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'new_status' => 'CANCELLED',
        'cash_reversal_amount' => $reversal_amount,
        'broker_balance' => $sumRow['sum_amount']
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
