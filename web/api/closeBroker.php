<?php
/**
 * closeBroker.php
 *
 * Securely closes a broker_account.
 *
 * This endpoint performs strict financial validation
 * before allowing closure.
 *
 * A broker_account can only be closed if:
 *   1. It belongs to the authenticated user.
 *   2. Its status is ACTIVE.
 *   3. Total associated cash balance is zero.
 *   4. No open positions remain (net quantity per instrument = 0).
 *
 * On success:
 *   - status is set to 'CLOSED'
 *   - closed_at is set to NOW()
 *
 * No physical deletion occurs.
 *
 * Authentication required.
 */

header('Content-Type: application/json; charset=utf-8');

define('CASHCUE_APP', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

try {

    // =====================================================
    // Authentication
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
        throw new Exception('Broker is not active.');
    }

    // =====================================================
    // 2️⃣ Validate total cash balance
    // =====================================================
    $stmtCash = $pdo->prepare("
        SELECT COALESCE(SUM(current_balance), 0) AS total_cash
        FROM cash_account
        WHERE broker_account_id = :broker_id
    ");

    $stmtCash->execute([
        ':broker_id' => $broker_id
    ]);

    $cashData   = $stmtCash->fetch(PDO::FETCH_ASSOC);
    $total_cash = floatval($cashData['total_cash']);

    if ($total_cash != 0.0) {
        throw new Exception(
            'Cannot close broker: remaining cash balance = ' . number_format($total_cash, 2)
        );
    }

    // =====================================================
    // 3️⃣ Validate open positions
    // =====================================================
    $stmtPositions = $pdo->prepare("
        SELECT COUNT(*) AS open_count
        FROM (
            SELECT instrument_id,
                   SUM(
                       CASE
                           WHEN type = 'BUY'  THEN quantity
                           WHEN type = 'SELL' THEN -quantity
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

    $positionData   = $stmtPositions->fetch(PDO::FETCH_ASSOC);
    $open_positions = intval($positionData['open_count']);

    if ($open_positions > 0) {
        throw new Exception(
            'Cannot close broker: ' . $open_positions . ' open position(s) remain.'
        );
    }

    // =====================================================
    // 4️⃣ Transaction — close broker
    // =====================================================
    $pdo->beginTransaction();

    $stmtUpdate = $pdo->prepare("
        UPDATE broker_account
        SET status = 'CLOSED',
            closed_at = NOW()
        WHERE id = :broker_id
    ");

    $stmtUpdate->execute([
        ':broker_id' => $broker_id
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Broker closed successfully.'
    ]);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}