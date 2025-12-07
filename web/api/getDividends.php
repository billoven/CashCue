<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = new Database('production');
    $pdo = $db->getConnection();

    $sql = "SELECT d.id, d.broker_id, b.name AS broker_name,
                   d.instrument_id, i.symbol, i.label,
                   d.payment_date, d.gross_amount, d.taxes_withheld,
                   d.amount, d.currency, d.created_at
            FROM dividend d
            LEFT JOIN broker_account b ON d.broker_id = b.id
            LEFT JOIN instrument i ON d.instrument_id = i.id
            WHERE 1=1";

    $params = [];

    if (!empty($_GET['broker_id'])) {
        $sql .= " AND d.broker_id = :broker_id";
        $params[':broker_id'] = $_GET['broker_id'];
    }
    if (!empty($_GET['instrument_id'])) {
        $sql .= " AND d.instrument_id = :instrument_id";
        $params[':instrument_id'] = $_GET['instrument_id'];
    }
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $sql .= " AND d.payment_date BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $_GET['start_date'];
        $params[':end_date'] = $_GET['end_date'];
    }

    $sql .= " ORDER BY d.payment_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $dividends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $dividends]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
