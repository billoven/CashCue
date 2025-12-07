<?php
require_once __DIR__ . '/../config/Database.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) throw new Exception('Missing id');

    $id = intval($_GET['id']);
    $db = new Database();
    $pdo = $db->getConnection();

    // get broker_id for update after delete
    $q = $pdo->prepare("SELECT broker_account_id FROM cash_transaction WHERE id = :id");
    $q->execute([':id' => $id]);
    $r = $q->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("DELETE FROM cash_transaction WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($r) {
        $broker_id = $r['broker_account_id'];
        $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_id");
        $sumStmt->execute([':broker_id' => $broker_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        $upd = $pdo->prepare("UPDATE cash_account SET current_balance = :bal, updated_at = NOW() WHERE broker_id = :broker_id");
        $upd->execute([':bal' => $sumRow['sum_amount'], ':broker_id' => $broker_id]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
