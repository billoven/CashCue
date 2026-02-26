<?php
// ==============================
// This API endpoint allows you to add a dividend record for a specific broker account and instrument. It also handles the creation of a corresponding cash transaction if the broker account has a linked cash account. The endpoint expects JSON input with the necessary fields to create the dividend and cash transaction records in the database.
// The required fields are broker_account_id, instrument_id, and payment_date. The amount can either be provided directly or calculated from gross_amount and taxes_withheld. If the broker account has a cash account, a cash transaction of type 'DIVIDEND' will be created with the net amount (amount) on the payment date. The current balance of the cash account will also be updated accordingly.
// The endpoint returns a JSON response indicating success or failure, along with the ID of the newly created dividend record if successful.
// ==============================
// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

// ==============================
// Input handling and validation
// ==============================
try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !is_array($input)) {
        throw new Exception('Missing or invalid JSON input');
    }

    // Required fields
    $required = ['broker_account_id','instrument_id','payment_date'];
    foreach ($required as $r) {
        if (!isset($input[$r]) || $input[$r] === '') {
            throw new Exception("Missing required field: $r");
        }
    }

    $broker_account_id = (int)$input['broker_account_id'];
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
        (broker_account_id, instrument_id, amount, gross_amount, currency, payment_date, taxes_withheld)
        VALUES (:broker_account_id, :instrument_id, :amount, :gross_amount, :currency, :payment_date, :taxes_withheld)
    ");
    $stmt->execute([
        ':broker_account_id' => $broker_account_id,
        ':instrument_id' => $instrument_id,
        ':amount' => $amount,
        ':gross_amount' => $gross_amount,
        ':currency' => $currency,
        ':payment_date' => $payment_date,
        ':taxes_withheld' => $taxes_withheld
    ]);

    $dividend_id = (int)$pdo->lastInsertId();

    // If broker uses cash account, create cash_transaction (amount is net)
    $chk = $pdo->prepare("SELECT has_cash_account FROM broker_account WHERE id = :broker_account_id LIMIT 1");
    $chk->execute([':broker_account_id' => $broker_account_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    if ($row && (int)$row['has_cash_account'] === 1) {
        $ins = $pdo->prepare("
            INSERT INTO cash_transaction
            (broker_account_id, date, amount, type, reference_id, comment)
            VALUES (:broker_account_id, :date, :amount, 'DIVIDEND', :reference_id, NULL)
        ");
        // date in cash_transaction uses datetime; use payment_date at midnight
        $cash_date = $payment_date . ' 00:00:00';
        $ins->execute([
            ':broker_account_id' => $broker_account_id,
            ':date' => $cash_date,
            ':amount' => $amount,
            ':reference_id' => $dividend_id
        ]);

        // update cash_account.current_balance if cash_account exists
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_account_id");
        $sumStmt->execute([':broker_account_id' => $broker_account_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        if ($sumRow) {
            $upd = $pdo->prepare("UPDATE cash_account SET current_balance = :bal, updated_at = NOW() WHERE broker_account_id = :broker_account_id");
            $upd->execute([':bal' => $sumRow['sum_amount'], ':broker_account_id' => $broker_account_id]);
        }
    }

    $pdo->commit();

    echo json_encode(['success' => true, 'dividend_id' => $dividend_id]);

} catch (Exception $e) {
    // Rollback transaction if something went wrong
    // We check if $pdo is set and if we're in a transaction before attempting to roll back, to avoid errors in case the connection failed or the transaction was never started.
     if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);    
}

