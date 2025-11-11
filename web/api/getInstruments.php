<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('development');
    $pdo = $db->getConnection();

    $stmt = $pdo->query("SELECT id, symbol, label, isin, type, currency FROM instrument ORDER BY label ASC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "count"  => count($rows),
        "data"   => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
