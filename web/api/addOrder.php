<?php
// ============================================================
// addOrder.php — Secure Order Creation API
// ============================================================
//
// Responsibilities:
//  - Accept JSON input for creating a new order
//  - Validate required fields and types
//  - Verify broker account existence and cash account if needed
//  - Verify instrument exists and is ACTIVE
//  - Insert order_transaction and optional cash_transaction
//  - Compute updated broker cash balance
//  - Fully rollback on error
//
// Notes:
//  - Front-end should still filter instruments for UX
//  - Server-side validation ensures security against malicious requests
// ============================================================

// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // ------------------------------------------------------------
    // Parse JSON input
    // ------------------------------------------------------------
    $inputJson = file_get_contents('php://input');
    $input = json_decode($inputJson, true);
    if (!$input || !is_array($input)) {
        throw new Exception('Missing or invalid JSON input');
    }

    // ------------------------------------------------------------
    // Validate required fields
    // ------------------------------------------------------------
    $required = ['broker_account_id','instrument_id','order_type','quantity','price','trade_date'];
    foreach ($required as $r) {
        if (!isset($input[$r]) || $input[$r] === '') {
            throw new Exception("Missing required field: $r");
        }
    }

    $broker_account_id = (int)$input['broker_account_id'];
    $instrument_id     = (int)$input['instrument_id'];
    $order_type        = strtoupper($input['order_type']);
    $quantity          = (float)$input['quantity'];
    $price             = (float)$input['price'];
    $fees              = isset($input['fees']) ? (float)$input['fees'] : 0.0;
    $trade_date        = $input['trade_date'];
    // $settled           = isset($input['settled']) ? (int)$input['settled'] : 1;
    $settled           = ($order_type === 'BUY') ? 1 : ($input['settled'] ?? 0);
    $comment            = trim($input['comment'] ?? null);

    if (!in_array($order_type, ['BUY','SELL'])) {
        throw new Exception("Invalid order_type");
    }

    // ------------------------------------------------------------
    // DB connection & transaction
    // ------------------------------------------------------------
    $db  = new Database();
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // ------------------------------------------------------------
    // Verify broker account exists (LOCK row to prevent race condition)
    // ------------------------------------------------------------
    $brokerStmt = $pdo->prepare("
        SELECT id, has_cash_account
        FROM broker_account
        WHERE id = :id
        FOR UPDATE
    ");
    $brokerStmt->execute([':id' => $broker_account_id]);
    $brokerRow = $brokerStmt->fetch(PDO::FETCH_ASSOC);

    if (!$brokerRow) {
        throw new Exception("Broker account not found.");
    }

    // ------------------------------------------------------------
    // Verify instrument exists and is ACTIVE
    // ------------------------------------------------------------
    $instStmt = $pdo->prepare("SELECT status FROM instrument WHERE id = :id LIMIT 1");
    $instStmt->execute([':id' => $instrument_id]);
    $instRow = $instStmt->fetch(PDO::FETCH_ASSOC);
    if (!$instRow) {
        throw new Exception("Instrument not found.");
    }
    if ($instRow['status'] !== 'ACTIVE') {
        throw new Exception("Cannot create order: instrument status is '{$instRow['status']}'");
    }

    // ------------------------------------------------------------
    // BUSINESS RULE: Check sufficient cash for BUY
    // ------------------------------------------------------------
    if ($order_type === 'BUY' && (int)$brokerRow['has_cash_account'] === 1) {

        $requiredAmount = round($quantity * $price + $fees, 2);

        // Lock cash_account row
        $cashStmt = $pdo->prepare("
            SELECT current_balance
            FROM cash_account
            WHERE broker_account_id = :broker_account_id
            FOR UPDATE
        ");
        $cashStmt->execute([':broker_account_id' => $broker_account_id]);
        $cashRow = $cashStmt->fetch(PDO::FETCH_ASSOC);

        if (!$cashRow) {
            throw new Exception("Cash account not found for this broker.");
        }

        $cashAvailable = (float)$cashRow['current_balance'];

        if ($cashAvailable < $requiredAmount) {

            $missing = $requiredAmount - $cashAvailable;
            $missingRounded = ceil($missing);

            throw new Exception(
                "Insufficient cash balance. You need an additional €{$missingRounded} to execute this BUY order."
            );
        }
    }


    // ------------------------------------------------------------
    // Insert order_transaction
    // ------------------------------------------------------------
    $stmt = $pdo->prepare("
        INSERT INTO order_transaction
        (
            broker_account_id,
            instrument_id,
            order_type,
            quantity,
            price,
            fees,
            trade_date,
            settled,
            status,
            cancelled_at,
            comment
        )
        VALUES
        (
            :broker_account_id,
            :instrument_id,
            :order_type,
            :quantity,
            :price,
            :fees,
            :trade_date,
            :settled,
            :status,
            :cancelled_at,
            :comment
        )
    ");
    $stmt->execute([
        ':broker_account_id' => $broker_account_id,
        ':instrument_id'     => $instrument_id,
        ':order_type'        => $order_type,
        ':quantity'          => $quantity,
        ':price'             => $price,
        ':fees'              => $fees,
        ':trade_date'        => $trade_date,
        ':settled'           => $settled,
        ':status'            => 'ACTIVE',
        ':cancelled_at'      => null,
        ':comment'           => $comment
    ]);
    $order_id = (int)$pdo->lastInsertId();

    // ------------------------------------------------------------
    // Optional cash transaction if broker has cash account
    // ------------------------------------------------------------
    if ((int)$brokerRow['has_cash_account'] === 1) {

        // --------------------------------------------------------
        // Compute cash impact of the order
        // BUY  → negative cash (outflow)
        // SELL → positive cash (inflow)
        // --------------------------------------------------------
        $grossAmount = round($quantity * $price, 2);

        if ($order_type === 'BUY') {
            $cashImpact = -round($grossAmount + $fees, 2);
        } else { // SELL
            $cashImpact = round($grossAmount - $fees, 2);
        }

        $cashComment = sprintf(
            'Order %s - initial cash impact',
            $order_type
        );

        // --------------------------------------------------------
        // Insert cash transaction (traceability layer)
        // --------------------------------------------------------
        $ins = $pdo->prepare("
            INSERT INTO cash_transaction
            (
                broker_account_id,
                date,
                amount,
                type,
                reference_id,
                comment
            )
            VALUES
            (
                :broker_account_id,
                :date,
                :amount,
                :type,
                :reference_id,
                :comment
            )
        ");

        $ins->execute([
            ':broker_account_id' => $broker_account_id,
            ':date'              => $trade_date,
            ':amount'            => $cashImpact,
            ':type'              => $order_type,
            ':reference_id'      => $order_id,
            ':comment'           => $cashComment
        ]);

        // --------------------------------------------------------
        // Incremental balance update (O(1) operation)
        // Avoid full SUM() recomputation for performance & scalability
        // Row already locked earlier with FOR UPDATE
        // --------------------------------------------------------
        $upd = $pdo->prepare("
            UPDATE cash_account
            SET 
                current_balance = current_balance + :impact,
                updated_at = NOW()
            WHERE broker_account_id = :broker_account_id
        ");

        $upd->execute([
            ':impact'            => $cashImpact,
            ':broker_account_id' => $broker_account_id
        ]);
    }

    // ------------------------------------------------------------
    // Commit transaction (atomic operation)
    // ------------------------------------------------------------
    $pdo->commit();
    ob_end_clean();
    echo json_encode([
        'success'  => true,
        'order_id' => $order_id
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
