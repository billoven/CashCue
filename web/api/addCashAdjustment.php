<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../include/CashUtils.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid HTTP method');
    }

    if (!isset($_POST['broker_id'])) {
        throw new Exception('Missing broker_id');
    }

    if (!isset($_POST['amount'])) {
        throw new Exception('Missing amount');
    }

    $broker_id = intval($_POST['broker_id']);
    if ($broker_id <= 0) {
        throw new Exception('Invalid broker_id');
    }

    $amount = floatval($_POST['amount']);
    if ($amount == 0.0) {
        throw new Exception('Amount cannot be zero');
    }

    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : null;
    $transaction_date = $_POST['transaction_date'] ?? null;

    $db = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    /* Vérifie que le cash_account existe */
    $stmt = $pdo->prepare("
        SELECT broker_account_id
        FROM cash_account
        WHERE broker_account_id = :broker_id
        LIMIT 1
    ");
    $stmt->execute([':broker_id' => $broker_id]);

    if (!$stmt->fetch()) {
        throw new Exception('Cash account not found');
    }

    /* Insertion de l'ajustement */
    $sql = "
        INSERT INTO cash_transaction
        (
            broker_account_id,
            transaction_date,
            type,
            amount,
            reference_type,
            reference_id,
            comment
        )
        VALUES
        (
            :broker_id,
            :transaction_date,
            'ADJUSTMENT',
            :amount,
            'manual',
            NULL,
            :comment
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':broker_id'       => $broker_id,
        ':transaction_date'=> $transaction_date ?? date('Y-m-d H:i:s'),
        ':amount'          => $amount,
        ':comment'         => $comment
    ]);

    /* Recalcul du solde */
    recalculateCashBalance($pdo, $broker_id);

    $pdo->commit();

    echo json_encode([
        'status'    => 'ok',
        'broker_id' => $broker_id,
        'amount'    => $amount
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
