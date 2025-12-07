<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'])) {
        throw new Exception('Missing dividend id in input');
    }

    $div_id = (int)$input['id'];

    $db = new Database();
    $pdo = $db->getConnection();

    $pdo->beginTransaction();

    // fetch existing dividend
    $sel = $pdo->prepare("SELECT * FROM dividend WHERE id = :id LIMIT 1");
    $sel->execute([':id' => $div_id]);
    $existing = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$existing) throw new Exception('Dividend not found');

    $broker_id = (int)$existing['broker_id'];

    // Allowed fields to update
    $allowed = ['instrument_id','amount','gross_amount','currency','payment_date','taxes_withheld'];
    $fields = [];
    $params = [':id' => $div_id];

    foreach ($allowed as $f) {
        if (array_key_exists($f, $input)) {
            $fields[] = "$f = :$f";
            $params[":$f"] = $input[$f];
        }
    }

    if (empty($fields)) throw new Exception('No fields to update');

    $sql = "UPDATE dividend SET " . implode(', ', $fields) . " WHERE id = :id";
    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    // Remove existing cash_transaction(s) referencing this dividend
    $del = $pdo->prepare("DELETE FROM cash_transaction WHERE reference_id = :ref AND type = 'DIVIDEND'");
    $del->execute([':ref' => $div_id]);

    // Recreate cash transaction if broker has cash_account
    $chk = $pdo->prepare("SELECT has_cash_account FROM broker_account WHERE id = :broker_id LIMIT 1");
    $chk->execute([':broker_id' => $broker_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    // Determine new values using input when provided, otherwise fallback to existing
    $amount = array_key_exists('amount', $input) ? (float)$input['amount'] : (float)$existing['amount'];
    $gross_amount = array_key_exists('gross_amount', $input) ? (float)$input['gross_amount'] : (float)$existing['gross_amount'];
    $taxes_withheld = array_key_exists('taxes_withheld', $input) ? (float)$input['taxes_withheld'] : (float)$existing['taxes_withheld'];
    $payment_date = array_key_exists('payment_date', $input) ? $input['payment_date'] : $existing['payment_date'];
    // If amount null and gross provided, compute net
    if ($amount === null || $amount === 0.0) {
        if ($gross_amount !== null) {
            $amount = $gross_amount - $taxes_withheld;
        }
    }

    if ($row && (int)$row['has_cash_account'] === 1) {
        $ins = $pdo->prepare("
            INSERT INTO cash_transaction
            (broker_account_id, date, amount, type, reference_id, comment)
            VALUES (:broker_id, :date, :amount, 'DIVIDEND', :reference_id, NULL)
        ");
        $cash_date = $payment_date . ' 00:00:00';
        $ins->execute([
            ':broker_id' => $broker_id,
            ':date' => $cash_date,
            ':amount' => $amount,
            ':reference_id' => $div_id
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

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
