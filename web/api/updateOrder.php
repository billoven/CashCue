<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'])) {
        throw new Exception('Missing order id in input');
    }

    $order_id = (int)$input['id'];

    $db = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    // Fetch existing order
    $sel = $pdo->prepare("SELECT * FROM order_transaction WHERE id = :id LIMIT 1");
    $sel->execute([':id' => $order_id]);
    $existing = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        throw new Exception('Order not found');
    }

    $broker_id = (int)$existing['broker_id'];

    // Determine final values (input overrides existing)
    $order_type = array_key_exists('order_type', $input)
        ? strtoupper($input['order_type'])
        : strtoupper($existing['order_type']);

    if (!in_array($order_type, ['BUY', 'SELL'])) {
        throw new Exception('Invalid order_type');
    }

    $quantity   = array_key_exists('quantity', $input) ? (float)$input['quantity'] : (float)$existing['quantity'];
    $price      = array_key_exists('price', $input)    ? (float)$input['price']    : (float)$existing['price'];
    $fees       = array_key_exists('fees', $input)     ? (float)$input['fees']     : (float)$existing['fees'];
    $trade_date = array_key_exists('trade_date', $input) ? $input['trade_date'] : $existing['trade_date'];
    $settled    = array_key_exists('settled', $input)  ? (int)$input['settled']    : (int)$existing['settled'];
    $instrument_id = array_key_exists('instrument_id', $input)
        ? (int)$input['instrument_id']
        : (int)$existing['instrument_id'];

    // Recalculate total_cost
    if ($order_type === 'BUY') {
        $total_cost = round($quantity * $price + $fees, 2);
        $cash_amount = -abs($total_cost);
        $cash_type = 'BUY';
    } else {
        $total_cost = round($quantity * $price - $fees, 2);
        $cash_amount = abs($total_cost);
        $cash_type = 'SELL';
    }

    // Update order_transaction
    $upd = $pdo->prepare("
        UPDATE order_transaction
        SET instrument_id = :instrument_id,
            order_type    = :order_type,
            quantity      = :quantity,
            price         = :price,
            fees          = :fees,
            trade_date    = :trade_date,
            settled       = :settled
        WHERE id = :id
    ");
    $upd->execute([
        ':instrument_id' => $instrument_id,
        ':order_type'    => $order_type,
        ':quantity'      => $quantity,
        ':price'         => $price,
        ':fees'          => $fees,
        ':trade_date'    => $trade_date,
        ':settled'       => $settled,
        ':id'            => $order_id
    ]);

    // Remove existing cash transaction(s)
    $del = $pdo->prepare("
        DELETE FROM cash_transaction
        WHERE reference_id = :ref
          AND type IN ('BUY','SELL')
    ");
    $del->execute([':ref' => $order_id]);

    // Recreate cash transaction if broker has cash account
    $chk = $pdo->prepare("SELECT has_cash_account FROM broker_account WHERE id = :broker_id");
    $chk->execute([':broker_id' => $broker_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    if ($row && (int)$row['has_cash_account'] === 1) {
        $ins = $pdo->prepare("
            INSERT INTO cash_transaction
                (broker_account_id, date, amount, type, reference_id, comment)
            VALUES
                (:broker_id, :date, :amount, :type, :reference_id, NULL)
        ");
        $ins->execute([
            ':broker_id'   => $broker_id,
            ':date'        => $trade_date,
            ':amount'      => $cash_amount,
            ':type'        => $cash_type,
            ':reference_id'=> $order_id
        ]);

        // Update cash_account.current_balance
        $sumStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount),0) AS sum_amount
            FROM cash_transaction
            WHERE broker_account_id = :broker_id
        ");
        $sumStmt->execute([':broker_id' => $broker_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);

        $upd2 = $pdo->prepare("
            UPDATE cash_account
            SET current_balance = :bal,
                updated_at = NOW()
            WHERE broker_id = :broker_id
        ");
        $upd2->execute([
            ':bal' => $sumRow['sum_amount'],
            ':broker_id' => $broker_id
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $order_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

