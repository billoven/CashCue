<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

if (!isset($_GET['instrument_id'])) {
    echo json_encode(["status" => "error", "message" => "instrument_id required"]);
    exit;
}

try {
    $db = new Database('production');           // ← créer une instance
    $pdo = $db->getConnection();    // ← appeler la méthode
    $instrument_id = (int) $_GET['instrument_id'];

    // Select all realtime prices for today, ordered by timestamp
    $sql = "
        SELECT price, captured_at
        FROM realtime_price
        WHERE instrument_id = :instrument_id
          AND DATE(captured_at) = CURDATE()
        ORDER BY captured_at ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(["instrument_id" => $instrument_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "count" => count($rows),
        "data" => $rows
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
