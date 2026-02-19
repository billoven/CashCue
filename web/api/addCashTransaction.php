<?php
// This endpoint is responsible for adding a new cash transaction linked to a broker account. It expects a JSON payload with the following structure:
/*
{
    "broker_account_id": 1, // ID of the broker account to link the cash transaction to
    "amount": 100.00, // Amount of the transaction (positive for deposits, negative for withdrawals)
    "type": "DEPOSIT", // Type of the transaction (e.g., DEPOSIT, WITHDRAWAL, FEES, ADJUSTMENT)
    "date": "2024-06-01 14:30", // Date and time of the transaction
    "comment": "Optional comment about the transaction",
    "reference_id": "Optional reference ID for linking to other records (e.g., trades, external system IDs)"
}
*/
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

try {
    // --------------------------------------------------
    // Decode input
    // --------------------------------------------------
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Missing input');

    // --------------------------------------------------
    // Required fields
    // --------------------------------------------------
    $required = ['broker_account_id','amount','type','date'];
    foreach ($required as $r) {
        if (!isset($input[$r])) throw new Exception("Missing $r");
    }

    // --------------------------------------------------
    // Define allowed manual cash types
    // --------------------------------------------------
    $MANUAL_CASH_TYPES = ['DEPOSIT', 'WITHDRAWAL', 'FEES', 'ADJUSTMENT'];

    // --------------------------------------------------
    // Validate type
    // --------------------------------------------------
    $type = strtoupper(trim($input['type']));
    if (!in_array($type, $MANUAL_CASH_TYPES, true)) {
        throw new Exception("Invalid manual cash type");
    }

    // --------------------------------------------------
    // Validate and normalize date (manual cash: minute precision)
    // Expected format: Y-m-d H:i
    // --------------------------------------------------
    $dateObj = DateTime::createFromFormat('Y-m-d H:i', $input['date']);

    if (!$dateObj) {
        throw new Exception("Invalid date format, expected Y-m-d H:i");
    }

    // Normalize seconds to zero
    $date = $dateObj->format('Y-m-d H:i:00');


    // --------------------------------------------------
    // Validate amount and enforce sign according to type
    // --------------------------------------------------
    $amount = floatval($input['amount']);
    if ($amount === 0.0) {
        throw new Exception("Amount cannot be zero");
    }

    switch ($type) {
        case 'DEPOSIT':
            if ($amount <= 0) throw new Exception("DEPOSIT amount must be positive");
            break;
        case 'WITHDRAWAL':
        case 'FEES':
            if ($amount >= 0) throw new Exception("$type amount must be negative");
            break;
        case 'ADJUSTMENT':
            // any non-zero value allowed
            break;
    }

    // Optional fields
    $comment = isset($input['comment']) ? trim($input['comment']) : null;
    $reference_id = isset($input['reference_id']) ? $input['reference_id'] : null;

    // --------------------------------------------------
    // Database connection
    // --------------------------------------------------
    $db = new Database();
    $pdo = $db->getConnection();

    // --------------------------------------------------
    // Insert cash transaction
    // --------------------------------------------------
    $stmt = $pdo->prepare("
        INSERT INTO cash_transaction (broker_account_id, date, amount, type, reference_id, comment)
        VALUES (:broker_account_id, :date, :amount, :type, :reference_id, :comment)
    ");
    $stmt->execute([
        ':broker_account_id' => $input['broker_account_id'],
        ':date' => $date,
        ':amount' => $amount,
        ':type' => $type,
        ':reference_id' => $reference_id,
        ':comment' => $comment
    ]);

    // --------------------------------------------------
    // Update current_balance in cash_account
    // --------------------------------------------------
    $broker_account_id = $input['broker_account_id'];
    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_account_id");
    $sumStmt->execute([':broker_account_id' => $broker_account_id]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);

    if ($sumRow) {
        $upd = $pdo->prepare("UPDATE cash_account SET current_balance = :bal, updated_at = NOW() WHERE broker_account_id = :broker_account_id");
        $upd->execute([
            ':bal' => $sumRow['sum_amount'],
            ':broker_account_id' => $broker_account_id
        ]);
    }

    // --------------------------------------------------
    // Return success
    // --------------------------------------------------
    echo json_encode(['success'=>true, 'id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
