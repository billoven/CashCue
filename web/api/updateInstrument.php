<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('production');
    $pdo = $db->getConnection();

    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || empty($data['id'])) {
        throw new Exception("Invalid or missing instrument ID.");
    }

    $sql = "UPDATE instrument 
            SET symbol = :symbol,
                isin = :isin,
                label = :label,
                type = :type,
                currency = :currency
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':symbol'   => $data['symbol'] ?? null,
        ':isin'     => $data['isin'] ?? null,
        ':label'    => $data['label'] ?? null,
        ':type'     => $data['type'] ?? null,
        ':currency' => $data['currency'] ?? null,
        ':id'       => $data['id']
    ]);

    echo json_encode([
        "status"  => "success",
        "message" => "Instrument updated successfully"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}

