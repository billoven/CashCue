<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');


try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid HTTP method');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id'])) {
        throw new Exception('Missing cash transaction id');
    }

    $id = (int)$input['id'];

    $db  = new Database();
    $pdo = $db->getConnection();

    // --------------------------------------------------
    // Start transaction (cash consistency is critical)
    // --------------------------------------------------
    $pdo->beginTransaction();

    // --------------------------------------------------
    // Fetch transaction to ensure it exists
    // --------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT broker_account_id, type
        FROM cash_transaction
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $transaction = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$transaction) {
        throw new Exception('Cash transaction not found');
    }

    $brokerAccountId = (int) $transaction['broker_account_id'];

    // --------------------------------------------------
    // Delete cash transaction
    // --------------------------------------------------
    $delStmt = $pdo->prepare("
        DELETE FROM cash_transaction
        WHERE id = :id
    ");
    $delStmt->execute([':id' => $id]);

    // --------------------------------------------------
    // Recalculate cash balance
    // --------------------------------------------------
    $sumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) AS balance
        FROM cash_transaction
        WHERE broker_account_id = :broker_account_id
    ");
    $sumStmt->execute([':broker_account_id' => $brokerAccountId]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);

    $balance = (float) $sumRow['balance'];

    // --------------------------------------------------
    // Update cash account balance
    // --------------------------------------------------
    $updStmt = $pdo->prepare("
        UPDATE cash_account
        SET current_balance = :balance,
            updated_at = NOW()
        WHERE broker_account_id = :broker_account_id
    ");
    $updStmt->execute([
        ':balance'            => $balance,
        ':broker_account_id'  => $brokerAccountId
    ]);

    // --------------------------------------------------
    // Commit
    // --------------------------------------------------
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
