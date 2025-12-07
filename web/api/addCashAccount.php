<?php
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['broker_id']) || !isset($data['name'])) {
        throw new Exception("Missing required fields");
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        INSERT INTO cash_account (broker_id, name, initial_balance, current_balance, created_at)
        VALUES (:broker_id, :name, :initial_balance, :initial_balance, NOW())
    ");
    $stmt->execute([
        ":broker_id" => $data["broker_id"],
        ":name" => $data["name"],
        ":initial_balance" => $data["initial_balance"] ?? 0.00
    ]);

    echo json_encode(["success" => true, "id" => $pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()]);
}
