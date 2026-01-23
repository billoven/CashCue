<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../include/CashUtils.php';

header('Content-Type: application/json');

try {
    // --------------------------------------------------
    // Method
    // --------------------------------------------------
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid HTTP method');
    }

    // --------------------------------------------------
    // JSON input (STANDARD API BEHAVIOR)
    // --------------------------------------------------
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    // Fallback for form-encoded (tests, legacy calls)
    if (!$input || !is_array($input)) {
        $input = $_POST;
    }

    if (!is_array($input) || empty($input)) {
        throw new Exception('Missing input data');
    }


    if (!isset($input['broker_account_id'], $input['amount'])) {
        throw new Exception('Missing parameters');
    }

    $broker_account_id = (int) $input['broker_account_id'];
    $amount    = (float) $input['amount'];
    $comment   = $input['comment'] ?? null;
    $date      = $input['date'] ?? date('Y-m-d H:i:s');

    if ($broker_account_id <= 0) {
        throw new Exception('Invalid broker_account_id');
    }

    if ($amount == 0.0) {
        throw new Exception('Amount cannot be zero');
    }

    // --------------------------------------------------
    // DB
    // --------------------------------------------------
    $db  = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    // --------------------------------------------------
    // Insert adjustment as cash_transaction
    // --------------------------------------------------
    $stmt = $pdo->prepare("
        INSERT INTO cash_transaction (
            broker_account_id,
            type,
            amount,
            date,
            comment,
            reference_id
        ) VALUES (
            :broker_account_id,
            'ADJUSTMENT',
            :amount,
            :date,
            :comment,
            NULL
        )
    ");

    $stmt->execute([
        ':broker_account_id' => $broker_account_id,
        ':amount'    => $amount,
        ':date'      => $date,
        ':comment'   => $comment
    ]);

    // --------------------------------------------------
    // Recalculate balance
    // --------------------------------------------------
    recalculateCashBalance($pdo, $broker_account_id);

    $pdo->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Cash adjustment added'
    ]);
}
catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}

