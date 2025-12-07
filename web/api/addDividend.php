<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) {
        throw new Exception('Missing or invalid JSON input');
    }

    // Required fields
    $required = ['broker_id','instrument_id','payment_date'];
    foreach ($required as $r) {
        if (!isset($input[$r]) || $input[$r] === '') {
            throw new Exception("Missing required field: $r");
        }
    }

    $broker_id = (int)$input['broker_id'];
    $instrument_id = (int)$input['instrument_id'];
    $payment_date = $input['payment_date']; // expected YYYY-MM-DD

    // Optional numeric fields
    $amount = isset($input['amount']) ? (float)$input['amount'] : null;
    $gross_amount = isset($input['gross_amount']) ? (float)$input['gross_amount'] : null;
    $taxes_withheld = isset($input['taxes_withheld']) ? (float)$input['taxes_withheld'] : 0.0;
    $currency = isset($input['currency']) ? trim($input['currency']) : 'EUR';

    // If amount not provided, try to compute
    if ($amount === null) {
        if ($gross_amount !== null) {
            $amount = $gross_amount - $taxes_withheld;
        } else {
            throw new Exception("Either 'amount' or 'gross_amount' must be provided");
        }
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $pdo->beginTransaction();

    // Insert into dividend
    $stmt = $pdo->prepare("
        INSERT INTO dividend
        (broker_id, instrument_id, amount, gross_amount, currency, payment_date, taxes_withheld)
        VALUES (:broker_id, :instrument_id, :amount, :gross_amount, :currency, :payment_date, :taxes_withheld)
    ");
    $stmt->execute([
        ':broker_id' => $broker_id,
        ':instrument_id' => $instrument_id,
        ':amount' => $amount,
        ':gross_amount' => $gross_amount,
        ':currency' => $currency,
        ':payment_date' => $payment_date,
        ':taxes_withheld' => $taxes_withheld
    ]);

    $dividend_id = (int)$pdo->lastInsertId();

    // If broker uses cash account, create cash_transaction (amount is net)
    $chk = $pdo->prepare("SELECT has_cash_account FROM broker_account WHERE id = :broker_id LIMIT 1");
    $chk->execute([':broker_id' => $broker_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    if ($row && (int)$row['has_cash_account'] === 1) {
        $ins = $pdo->prepare("
            INSERT INTO cash_transaction
            (broker_account_id, date, amount, type, reference_id, comment)
            VALUES (:broker_id, :date, :amount, 'DIVIDEND', :reference_id, NULL)
        ");
        // date in cash_transaction uses datetime; use payment_date at midnight
        $cash_date = $payment_date . ' 00:00:00';
        $ins->execute([
            ':broker_id' => $broker_id,
            ':date' => $cash_date,
            ':amount' => $amount,
            ':reference_id' => $dividend_id
        ]);

        // update cash_account.current_balance if cash_account exists
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_id");
        $sumStmt->execute([':broker_id' => $broker_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        if ($sumRow) {
            $upd = $pdo->prepare("UPDATE cash_account SET current_balance = :bal, updated_at = NOW() WHERE broker_id = :broker_id");
            $upd->execute([':bal' => $sumRow['sum_amount'], ':broker_id' => $broker_id]);
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'dividend_id' => $dividend_id]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

