<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('development');           // ← créer une instance
    $pdo = $db->getConnection();    // ← appeler la méthode

    $sql = "
        SELECT 
            snapshot_date,
            total_value,
            invested_value,
            cash_balance
        FROM portfolio_snapshot
        ORDER BY snapshot_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "count" => count($rows), "data" => $rows]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
