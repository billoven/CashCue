<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['id'])) {
        throw new Exception("Missing order ID");
    }

    // 🧩 Validate fields
    $required = ['instrument_id', 'order_type', 'quantity', 'price', 'trade_date'];
    foreach ($required as $r) {
        if (empty($data[$r])) {
            throw new Exception("Missing required field: $r");
        }
    }

    $id            = (int) $data['id'];
    $instrument_id = (int) $data['instrument_id'];
    $order_type    = strtoupper(trim($data['order_type']));
    $quantity      = (float) $data['quantity'];
    $price         = (float) $data['price'];
    $fees          = isset($data['fees']) ? (float) $data['fees'] : 0.0;
    $trade_date    = $data['trade_date'];

    $db = new Database('development');
    $pdo = $db->getConnection();

    // 🔄 Update
    $sql = "
        UPDATE order_transaction
        SET instrument_id = :instrument_id,
            order_type    = :order_type,
            quantity      = :quantity,
            price         = :price,
            fees          = :fees,
            trade_date    = :trade_date
        WHERE id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':instrument_id' => $instrument_id,
        ':order_type'    => $order_type,
        ':quantity'      => $quantity,
        ':price'         => $price,
        ':fees'          => $fees,
        ':trade_date'    => $trade_date,
        ':id'            => $id
    ]);

    echo json_encode([
        "status"  => "success",
        "message" => "Order successfully updated"
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}
