<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['broker_account_id'])) {
        throw new Exception('Missing broker_account_id');
    }

    $broker_account_id = $_GET['broker_account_id'];

    $from = $_GET['from'] ?? null;   // YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
    $to   = $_GET['to']   ?? null;
    $type = $_GET['type'] ?? null;   // BUY, SELL, DIVIDEND, etc.

    $db = new Database();
    $pdo = $db->getConnection();

    $sql = "
        SELECT
            id,
            broker_account_id,
            date,
            type,
            amount,
            reference_id,
            comment
        FROM cash_transaction
        WHERE 1=1
    ";
    $params = [];

    if ($broker_account_id !== 'all') {
        $broker_account_id = (int)$broker_account_id;
        if ($broker_account_id <= 0) {
            throw new Exception('Invalid broker_account_id');
        }
        $sql .= " AND broker_account_id = :broker_account_id";
        $params[':broker_account_id'] = $broker_account_id;
    }

    if ($from) {
        $sql .= " AND date >= :from";
        $params[':from'] = $from;
    }

    if ($to) {
        $sql .= " AND date <= :to";
        $params[':to'] = $to;
    }

    if ($type) {
        $sql .= " AND type = :type";
        $params[':type'] = $type;
    }

    $sql .= " ORDER BY date DESC, id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'count'   => count($rows),
        'data'    => $rows
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

