<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || empty($data['symbol']) || empty($data['label'])) {
        throw new Exception("Missing required fields");
    }

    $db = new Database('development');
    $pdo = $db->getConnection();

    $sql = "INSERT INTO instrument (symbol, label, isin, type, currency)
            VALUES (:symbol, :label, :isin, :type, :currency)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':symbol'   => strtoupper(trim($data['symbol'])),
        ':label'    => trim($data['label']),
        ':isin'     => $data['isin'] ?? null,
        ':type'     => $data['type'] ?? 'stock',
        ':currency' => $data['currency'] ?? 'EUR'
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Instrument added successfully",
        "insert_id" => $pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

