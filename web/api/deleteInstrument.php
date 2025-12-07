<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    echo json_encode(["status" => "error", "message" => "Missing instrument ID"]);
    exit;
}

try {
    $db = new Database('production');
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("DELETE FROM instrument WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        "status" => "success",
        "message" => "Instrument deleted successfully"
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
