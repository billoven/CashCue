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

    // fetch existing order
    $sel = $pdo->prepare("SELECT * FROM order_transaction WHERE id = :id LIMIT 1");
    $sel->execute([':id' => $order_id]);
    $existing = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$existing) throw new Exception('Order not found');

    $broker_id = (int)$existing['broker_id'];

    // Allowed fields to update
    $allowed = ['instrument_id','order_type','quantity','price','fees','trade_date','settled'];
    $fields = [];
    $params = [':id' => $order_id];

    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            // Basic validation
            if ($f === 'order_type') {
                $val = strtoupper($input[$f]);
                if (!in_array($val, ['BUY','SELL'])) throw new Exception("Invalid order_type");
                $params[":$f"] = $val;
            } elseif (in_array($f, ['quantity','price','fees'])) {
                $params[":$f"] = (float)$input[$f];
            } elseif ($f === 'settled') {
                $params[":$f"] = (int)$input[$f];
            } else {
                $params[":$f"] = $input[$f];
            }
            $fields[] = "$f = :$f";
        }
    }

    if (empty($fields)) throw new Exception('No fields to update');

    $sql = "UPDATE order_transaction SET " . implode(', ', $fields) . " WHERE id = :id";
    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    // Remove existing cash_transaction(s) referencing this order
    $del = $pdo->prepare("DELETE FROM cash_transaction WHERE reference_id = :ref AND type IN ('BUY','SELL')");
    $del->execute([':ref' => $order_id]);

    // Recreate cash transaction if broker has cash_account
    $chk = $pdo->prepare("SELECT has_cash_account FROM broker_account WHERE id = :broker_id LIMIT 1");
    $chk->execute([':broker_id' => $broker_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    // Determine new values using input when provided, otherwise fallback to existing
    $order_type = array_key_exists('order_type', $input) ? strtoupper($input['order_type']) : strtoupper($existing['order_type']);
    $quantity = array_key_exists('quantity', $input) ? (float)$input['quantity'] : (float)$existing['quantity'];
    $price = array_key_exists('price', $input) ? (float)$input['price'] : (float)$existing['price'];
    $fees = array_key_exists('fees', $input) ? (float)$input['fees'] : (float)$existing['fees'];
    $trade_date = array_key_exists('trade_date', $input) ? $input['trade_date'] : $existing['trade_date'];

    if ($row && (int)$row['has_cash_account'] === 1) {
        if ($order_type === 'BUY') {
            $amt = round($quantity * $price + $fees, 2);
            $amount = -abs($amt);
            $ctype = 'BUY';
        } else {
            $amt = round($quantity * $price - $fees, 2);
            $amount = abs($amt);
            $ctype = 'SELL';
        }

        $cash_date = $trade_date;
        $ins = $pdo->prepare("
            INSERT INTO cash_transaction (broker_account_id, date, amount, type, reference_id, comment)
            VALUES (:broker_id, :date, :amount, :type, :reference_id, NULL)
        ");
        $ins->execute([
            ':broker_id' => $broker_id,
            ':date' => $cash_date,
            ':amount' => $amount,
            ':type' => $ctype,
            ':reference_id' => $order_id
        ]);

        // update cash_account.current_balance
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_id");
        $sumStmt->execute([':broker_id' => $broker_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        if ($sumRow) {
            $upd2 = $pdo->prepare("UPDATE cash_account SET current_balance = :bal, updated_at = NOW() WHERE broker_id = :broker_id");
            $upd2->execute([':bal' => $sumRow['sum_amount'], ':broker_id' => $broker_id]);
        }
    }

    $pdo->commit();

    // Retourner success + order_id
    echo json_encode([
        'success' => true,
        'order_id' => $order_id
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
