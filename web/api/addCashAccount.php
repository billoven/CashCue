<?php
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: application/json');

// This endpoint is responsible for adding a new cash account linked to a broker account. It expects a JSON payload with the following structure:
/*
{
    "broker_account_id": 1, // ID of the broker account to link the cash account to
    "name": "My Cash Account", // Name of the cash account
    "initial_balance": 1000.00 // Initial balance for the cash account (optional, defaults to 0.00)
}
*/ 
try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['broker_account_id']) || !isset($data['name'])) {
        throw new Exception("Missing required fields");
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        INSERT INTO cash_account (broker_account_id, name, initial_balance, current_balance, created_at)
        VALUES (:broker_account_id, :name, :initial_balance, :initial_balance, NOW())
    ");
    $stmt->execute([
        ":broker_account_id" => $data["broker_account_id"],
        ":name" => $data["name"],
        ":initial_balance" => $data["initial_balance"] ?? 0.00
    ]);

    echo json_encode(["success" => true, "id" => $pdo->lastInsertId()]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()]);
}
