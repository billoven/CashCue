<?php
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["id"])) {
        throw new Exception("Missing cash account id");
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $fields = [];
    $params = [":id" => $data["id"]];

    if (isset($data["name"])) {
        $fields[] = "name = :name";
        $params[":name"] = $data["name"];
    }

    if (isset($data["current_balance"])) {
        $fields[] = "current_balance = :current_balance";
        $params[":current_balance"] = $data["current_balance"];
    }

    if (empty($fields)) {
        throw new Exception("No fields to update");
    }

    $sql = "UPDATE cash_account SET " . implode(", ", $fields) . " WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()]);
}
