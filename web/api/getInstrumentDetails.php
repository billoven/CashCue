<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo json_encode(["status" => "error", "message" => "Missing instrument ID"]);
    exit;
}

try {
    $db = new Database('development');
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("SELECT id, symbol, label, isin, type, currency FROM instrument WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["status" => "error", "message" => "Instrument not found"]);
        exit;
    }

    echo json_encode(["status" => "success", "data" => $row]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
