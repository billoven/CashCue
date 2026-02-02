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

ob_start(); // buffer output
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/cashcue_php_errors.log');

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
    $settled           = isset($input['settled']) ? (int)$input['settled'] : 1;

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
    // Verify broker account exists
    // ------------------------------------------------------------
    $brokerStmt = $pdo->prepare("SELECT has_cash_account FROM broker_account WHERE id = :id LIMIT 1");
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
            cancelled_at
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
            :cancelled_at
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
        ':cancelled_at'      => null
    ]);
    $order_id = (int)$pdo->lastInsertId();

    // ------------------------------------------------------------
    // Optional cash transaction if broker has cash account
    // ------------------------------------------------------------
    if ((int)$brokerRow['has_cash_account'] === 1) {
        $amt = ($order_type === 'BUY') ? round($quantity*$price + $fees,2) : round($quantity*$price - $fees,2);
        $amount = ($order_type === 'BUY') ? -abs($amt) : abs($amt);
        $ctype  = $order_type;

        $ins = $pdo->prepare("
            INSERT INTO cash_transaction
            (broker_account_id, date, amount, type, reference_id, comment)
            VALUES (:broker_account_id, :date, :amount, :type, :reference_id, NULL)
        ");
        $ins->execute([
            ':broker_account_id' => $broker_account_id,
            ':date'              => $trade_date,
            ':amount'            => $amount,
            ':type'              => $ctype,
            ':reference_id'      => $order_id
        ]);

        // Update current balance
        $sumStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount),0) AS sum_amount
            FROM cash_transaction
            WHERE broker_account_id = :broker_account_id
        ");
        $sumStmt->execute([':broker_account_id' => $broker_account_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        if ($sumRow) {
            $upd = $pdo->prepare("
                UPDATE cash_account
                SET current_balance = :bal, updated_at = NOW()
                WHERE broker_account_id = :broker_account_id
            ");
            $upd->execute([
                ':bal' => $sumRow['sum_amount'],
                ':broker_account_id' => $broker_account_id
            ]);
        }
    }

    $pdo->commit();
    ob_end_clean(); // clear buffer
    echo json_encode(['success' => true, 'order_id' => $order_id]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
