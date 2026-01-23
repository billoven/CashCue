<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['id'])) throw new Exception('Missing id');

    $fields = [];
    $params = [':id' => $input['id']];

    if (isset($input['date'])) { $fields[] = 'date = :date'; $params[':date'] = $input['date']; }
    if (isset($input['amount'])) { $fields[] = 'amount = :amount'; $params[':amount'] = $input['amount']; }
    if (isset($input['type'])) { $fields[] = 'type = :type'; $params[':type'] = $input['type']; }
    if (array_key_exists('reference_id', $input)) { $fields[] = 'reference_id = :reference_id'; $params[':reference_id'] = $input['reference_id']; }
    if (array_key_exists('comment', $input)) { $fields[] = 'comment = :comment'; $params[':comment'] = $input['comment']; }

    if (empty($fields)) throw new Exception('No fields to update');

    $sql = "UPDATE cash_transaction SET " . implode(', ', $fields) . " WHERE id = :id";
    $db = new Database();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // update cash_account.current_balance if exists
    $tx = $pdo->prepare("SELECT broker_account_id FROM cash_transaction WHERE id = :id");
    $tx->execute([':id' => $input['id']]);
    $txr = $tx->fetch(PDO::FETCH_ASSOC);
    if ($txr) {
        $broker_account_id = $txr['broker_account_id'];
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_account_id");
        $sumStmt->execute([':broker_account_id' => $broker_account_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        $upd = $pdo->prepare("UPDATE cash_account SET current_balance = :bal, updated_at = NOW() WHERE broker_account_id = :broker_account_id");
        $upd->execute([':bal' => $sumRow['sum_amount'], ':broker_account_id' => $broker_account_id]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
