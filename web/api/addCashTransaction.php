<?php
require_once __DIR__ . '/../config/Database.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Missing input');

    $required = ['broker_account_id','amount','type'];
    foreach ($required as $r) {
        if (!isset($input[$r])) throw new Exception("Missing $r");
    }

    $date = isset($input['date']) ? $input['date'] : date('Y-m-d H:i:s');
    $comment = isset($input['comment']) ? $input['comment'] : null;
    $reference_id = isset($input['reference_id']) ? $input['reference_id'] : null;

    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        INSERT INTO cash_transaction (broker_account_id, date, amount, type, reference_id, comment)
        VALUES (:broker_account_id, :date, :amount, :type, :reference_id, :comment)
    ");
    $stmt->execute([
        ':broker_account_id' => $input['broker_account_id'],
        ':date' => $date,
        ':amount' => $input['amount'],
        ':type' => $input['type'],
        ':reference_id' => $reference_id,
        ':comment' => $comment
    ]);

    // Optionnel : update current_balance incrementally if cash_account exists
    $broker_id = $input['broker_account_id'];
    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_id");
    $sumStmt->execute([':broker_id' => $broker_id]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
    if ($sumRow) {
        $upd = $pdo->prepare("UPDATE cash_account SET current_balance = :bal, updated_at = NOW() WHERE broker_id = :broker_id");
        $upd->execute([':bal' => $sumRow['sum_amount'], ':broker_id' => $broker_id]);
    }

    echo json_encode(['success'=>true, 'id' => $pdo->lastInsertId()]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
