<?php
require_once __DIR__ . '/../config/Database.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['broker_id'])) throw new Exception('Missing broker_id');

    $broker_id = intval($_GET['broker_id']);
    $db = new Database();
    $pdo = $db->getConnection();

    // Prefer current_balance from cash_account if exist
    $stmt = $pdo->prepare("SELECT current_balance FROM cash_account WHERE broker_id = :broker_id LIMIT 1");
    $stmt->execute([':broker_id' => $broker_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $balance = (float)$row['current_balance'];
    } else {
        // compute from transactions
        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS sum_amount FROM cash_transaction WHERE broker_account_id = :broker_id");
        $s->execute([':broker_id' => $broker_id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        $balance = $r ? (float)$r['sum_amount'] : 0.00;
    }

    echo json_encode(['broker_id' => $broker_id, 'balance' => $balance]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
