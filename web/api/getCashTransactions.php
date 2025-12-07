<?php
require_once __DIR__ . '/../config/Database.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['broker_id'])) throw new Exception('Missing broker_id');

    $broker_id = intval($_GET['broker_id']);
    $from = isset($_GET['from']) ? $_GET['from'] : null;
    $to = isset($_GET['to']) ? $_GET['to'] : null;

    $db = new Database();
    $pdo = $db->getConnection();

    $sql = "SELECT * FROM cash_transaction WHERE broker_account_id = :broker_id";
    $params = [':broker_id' => $broker_id];

    if ($from) {
        $sql .= " AND date >= :from";
        $params[':from'] = $from;
    }
    if ($to) {
        $sql .= " AND date <= :to";
        $params[':to'] = $to;
    }

    $sql .= " ORDER BY date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['broker_id' => $broker_id, 'history' => $rows]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
