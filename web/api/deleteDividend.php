<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['id'])) throw new Exception('Missing id');

    $div_id = (int)$_GET['id'];

    $db = new Database();
    $pdo = $db->getConnection();

    $pdo->beginTransaction();

    // find dividend to get broker
    $sel = $pdo->prepare("SELECT broker_id FROM dividend WHERE id = :id LIMIT 1");
    $sel->execute([':id' => $div_id]);
    $row = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('Dividend not found');

    $broker_id = (int)$row['broker_id'];

    // delete dividend
    $stmt = $pdo->prepare("DELETE FROM dividend WHERE id = :id");
    $stmt->execute([':id' => $div_id]);

    // delete related cash_transaction(s)
    $del = $pdo->prepare("DELETE FROM cash_transaction WHERE reference_id = :ref AND type = 'DIVIDEND'");
    $del->execute([':ref' => $div_id]);

    // update cash_account.current_balance if exists
    $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_id");
    $sumStmt->execute([':broker_id' => $broker_id]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
    if ($sumRow) {
        $upd = $pdo->prepare("UPDATE cash_account SET current_balance = :bal, updated_at = NOW() WHERE broker_id = :broker_id");
        $upd->execute([':bal' => $sumRow['sum_amount'], ':broker_id' => $broker_id]);
    }

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
