<?php
/**
 * updateOrder.php
 *
 * Centralized update endpoint for order_transaction.
 *
 * Business rules:
 * - Comment can be modified at any time.
 * - Settled can move from 0 → 1 only (no revert).
 * - Financial fields (quantity, price, fees) are editable
 *   only on trade date and for ACTIVE orders.
 *
 * Update mode is automatically resolved based on payload.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    // ------------------------------------------------------------
    // Read & validate payload
    // ------------------------------------------------------------
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['order_id'])) {
        throw new Exception('Invalid or missing order_id');
    }

    $orderId = (int)$input['order_id'];
    $comment = isset($input['comment']) ? trim($input['comment']) : null;

    $hasQuantity = array_key_exists('quantity', $input);
    $hasPrice    = array_key_exists('price', $input);
    $hasFees     = array_key_exists('fees', $input);
    $hasSettled  = array_key_exists('settled', $input);

    // ------------------------------------------------------------
    // DB connection
    // ------------------------------------------------------------
    $db  = new Database('production');
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ------------------------------------------------------------
    // Start transaction immediately (locks require active TX)
    // ------------------------------------------------------------
    $pdo->beginTransaction();

    // ------------------------------------------------------------
    // Lock order row
    // ------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT *
        FROM order_transaction
        WHERE id = :id
        FOR UPDATE
    ");
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Order not found');
    }

    // ------------------------------------------------------------
    // Compute financial changes by comparing DB vs input
    // ------------------------------------------------------------
    $newQty   = $hasQuantity ? (float)$input['quantity'] : (float)$order['quantity'];
    $newPrice = $hasPrice    ? (float)$input['price']    : (float)$order['price'];
    $newFees  = $hasFees     ? (float)$input['fees']     : (float)$order['fees'];

    $isFinancialUpdate =
        ($newQty !== (float)$order['quantity']) ||
        ($newPrice !== (float)$order['price']) ||
        ($newFees !== (float)$order['fees']);

    $currentSettled = (int)$order['settled'];
    $newSettled     = $hasSettled ? (int)$input['settled'] : $currentSettled;
    $isSettledUpdate = $hasSettled && $newSettled !== $currentSettled;

    // ============================================================
    // MODE 1: COMMENT-ONLY UPDATE (ALWAYS ALLOWED)
    // ============================================================
    if (!$isFinancialUpdate && !$isSettledUpdate) {

        // Comment must be provided
        if ($comment === null || $comment === (string)$order['comment']) {
            throw new Exception('No changes detected');
        }

        $stmt = $pdo->prepare("
            UPDATE order_transaction
            SET comment = :comment
            WHERE id = :id
        ");
        $stmt->execute([
            'comment' => $comment,
            'id'      => $orderId
        ]);

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'mode'    => 'comment_only'
        ]);
        exit;
    }

    // ============================================================
    // MODE 2: SETTLED UPDATE
    // ============================================================
    if ($isSettledUpdate && !$isFinancialUpdate) {

        if ($currentSettled === 1 && $newSettled === 0) {
            throw new Exception('Reverting an executed order (settled = 1) is not allowed.');
        }

        if ($order['status'] !== 'ACTIVE') {
            throw new Exception('Settlement status can only be modified for ACTIVE orders.');
        }

        $stmt = $pdo->prepare("
            UPDATE order_transaction
            SET settled = :settled,
                comment = :comment
            WHERE id = :id
        ");
        $stmt->execute([
            'settled' => $newSettled,
            'comment' => $comment,
            'id'      => $orderId
        ]);

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'mode'    => 'settled_update'
        ]);
        exit;
    }

    // ============================================================
    // MODE 3: FINANCIAL CORRECTION (STRICT)
    // ============================================================
    if ($isFinancialUpdate) {

        if ($order['status'] !== 'ACTIVE') {
            throw new Exception('Only ACTIVE orders can be modified');
        }

        if (date('Y-m-d', strtotime($order['trade_date'])) !== date('Y-m-d')) {
            throw new Exception('Financial corrections allowed only on trade date');
        }

        if ($comment === null || $comment === '') {
            throw new Exception('Comment is mandatory for financial correction');
        }

        if ($newQty <= 0 || $newPrice <= 0 || $newFees < 0) {
            throw new Exception('Invalid numeric values');
        }
    }

    // ------------------------------------------------------------
    // Compute old/new amounts
    // ------------------------------------------------------------
    $oldQty   = (float)$order['quantity'];
    $oldPrice = (float)$order['price'];
    $oldFees  = (float)$order['fees'];

    if ($order['order_type'] === 'BUY') {
        $oldAmount = -round($oldQty * $oldPrice + $oldFees, 2);
        $newAmount = -round($newQty * $newPrice + $newFees, 2);
    } else { // SELL
        $oldAmount = round($oldQty * $oldPrice - $oldFees, 2);
        $newAmount = round($newQty * $newPrice - $newFees, 2);
    }

    $deltaCash = round($newAmount - $oldAmount, 2);

    // ------------------------------------------------------------
    // Lock cash account row
    // ------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT current_balance
        FROM cash_account
        WHERE broker_account_id = :broker_account_id
        FOR UPDATE
    ");
    $stmt->execute(['broker_account_id' => $order['broker_account_id']]);
    $cashRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cashRow) {
        throw new Exception('Cash account not found');
    }

    $currentBalance = (float)$cashRow['current_balance'];
    $newBalance     = $currentBalance + $deltaCash;

    // ------------------------------------------------------------
    // Security: Prevent negative balance for BUY increases
    // ------------------------------------------------------------
    if ($order['order_type'] === 'BUY' && $newBalance < 0) {
        $missing = ceil(abs($newBalance));
        throw new Exception("Insufficient cash. Missing approximately €{$missing}.");
    }

    // ------------------------------------------------------------
    // 1. Update order_transaction
    // ------------------------------------------------------------
    $stmt = $pdo->prepare("
        UPDATE order_transaction
        SET quantity = :quantity,
            price    = :price,
            fees     = :fees,
            comment  = :comment,
            settled  = :settled
        WHERE id = :id
    ");
    $stmt->execute([
        'quantity' => $newQty,
        'price'    => $newPrice,
        'fees'     => $newFees,
        'comment'  => $comment,
        'settled'  => $newSettled,
        'id'       => $orderId
    ]);

    // ------------------------------------------------------------
    // 2. Update cash_transaction
    // ------------------------------------------------------------
    $stmt = $pdo->prepare("
        UPDATE cash_transaction
        SET amount = :amount,
            comment = :comment
        WHERE reference_id = :order_id
          AND broker_account_id = :broker_account_id
    ");
    $stmt->execute([
        'amount'            => $newAmount,
        'comment'           => $comment,
        'order_id'          => $orderId,
        'broker_account_id' => $order['broker_account_id']
    ]);

    // ------------------------------------------------------------
    // 3. Incremental balance update
    // ------------------------------------------------------------
    $stmt = $pdo->prepare("
        UPDATE cash_account
        SET current_balance = current_balance + :delta,
            updated_at = NOW()
        WHERE broker_account_id = :broker_account_id
    ");
    $stmt->execute([
        'delta'             => $deltaCash,
        'broker_account_id' => $order['broker_account_id']
    ]);

    $pdo->commit();
    echo json_encode([
        'success'    => true,
        'mode'       => $isFinancialUpdate ? 'financial_update' : 'settled_update',
        'delta_cash' => $deltaCash
    ]);

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
