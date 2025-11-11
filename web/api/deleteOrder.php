<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    if (!isset($_GET["id"])) throw new Exception("Missing order ID");

    $id = (int) $_GET["id"];

    $db = new Database('development');
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("DELETE FROM order_transaction WHERE id = :id");
    $stmt->execute([":id" => $id]);

    echo json_encode(["status" => "success", "message" => "Order deleted successfully"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
