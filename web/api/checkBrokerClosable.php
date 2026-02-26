<?php
/**
 * checkBrokerClosable.php
 *
 * Validates whether a broker_account can be safely closed.
 *
 * Business validation rules:
 * ------------------------------------------------------------
 * A broker_account can only be closed if:
 *
 * 1. It belongs to the authenticated user.
 * 2. Its current status is ACTIVE.
 * 3. All associated cash_account balances are zero.
 * 4. No open positions remain (net quantity per instrument = 0).
 *
 * This endpoint DOES NOT modify any data.
 * It only performs validation checks and returns a structured JSON response.
 *
 * Expected POST payload:
 *   id (int) → broker_account.id
 *
 * Example success response:
 * {
 *   "success": true,
 *   "closable": true,
 *   "cash_balance": 0,
 *   "open_positions": 0
 * }
 *
 * Example failure response:
 * {
 *   "success": true,
 *   "closable": false,
 *   "cash_balance": 1250.50,
 *   "open_positions": 2
 * }
 *
 * Authentication required.
 */

header('Content-Type: application/json; charset=utf-8');

define('CASHCUE_APP', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

try {

    // =====================================================
    // Authentication check
    // =====================================================
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated.');
    }

    $user_id   = intval($_SESSION['user_id']);
    $broker_id = intval($_POST['id'] ?? 0);

    if ($broker_id <= 0) {
        throw new Exception('Invalid broker ID.');
    }

    $db = new Database('production');
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // =====================================================
    // 1️⃣ Verify ownership and status
    // =====================================================
    $stmtBroker = $pdo->prepare("
        SELECT b.status
        FROM broker_account b
        JOIN user_broker_account uba
          ON uba.broker_account_id = b.id
        WHERE b.id = :broker_id
          AND uba.user_id = :user_id
        LIMIT 1
    ");

    $stmtBroker->execute([
        ':broker_id' => $broker_id,
        ':user_id'   => $user_id
    ]);

    $broker = $stmtBroker->fetch(PDO::FETCH_ASSOC);

    if (!$broker) {
        throw new Exception('Broker not found or access denied.');
    }

    if ($broker['status'] !== 'ACTIVE') {
        echo json_encode([
            'success' => true,
            'closable' => false,
            'reason' => 'Broker is not active.',
            'cash_balance' => 0,
            'open_positions' => 0
        ]);
        exit;
    }

    // =====================================================
    // 2️⃣ Calculate total remaining cash balance
    // =====================================================
    $stmtCash = $pdo->prepare("
        SELECT COALESCE(SUM(current_balance), 0) AS total_cash
        FROM cash_account
        WHERE broker_account_id = :broker_id
    ");

    $stmtCash->execute([
        ':broker_id' => $broker_id
    ]);

    $cashData = $stmtCash->fetch(PDO::FETCH_ASSOC);
    $total_cash = floatval($cashData['total_cash']);

    // =====================================================
    // 3️⃣ Detect open positions (net quantity ≠ 0)
    // =====================================================
    $stmtPositions = $pdo->prepare("
        SELECT COUNT(*) AS open_count
        FROM (
            SELECT instrument_id,
                   SUM(
                       CASE
                           WHEN order_type = 'BUY'  THEN quantity
                           WHEN order_type = 'SELL' THEN -quantity
                           ELSE 0
                       END
                   ) AS net_qty
            FROM order_transaction
            WHERE broker_account_id = :broker_id
            GROUP BY instrument_id
            HAVING net_qty <> 0
        ) AS open_positions
    ");

    $stmtPositions->execute([
        ':broker_id' => $broker_id
    ]);

    $positionData = $stmtPositions->fetch(PDO::FETCH_ASSOC);
    $open_positions = intval($positionData['open_count']);

    // =====================================================
    // 4️⃣ Final decision
    // =====================================================
    $closable = ($total_cash == 0.0 && $open_positions === 0);

    echo json_encode([
        'success' => true,
        'closable' => $closable,
        'cash_balance' => $total_cash,
        'open_positions' => $open_positions
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}