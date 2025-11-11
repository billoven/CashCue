<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    // 🧩 Validate required fields
    $required = ['instrument_id', 'order_type', 'quantity', 'price', 'trade_date'];
    foreach ($required as $r) {
        if (empty($data[$r])) {
            throw new Exception("Missing required field: $r");
        }
    }

    // 🧮 Sanitize and assign
    $instrument_id = (int) $data['instrument_id'];
    $order_type    = strtoupper(trim($data['order_type']));
    $quantity      = (float) $data['quantity'];
    $price         = (float) $data['price'];
    $fees          = isset($data['fees']) ? (float) $data['fees'] : 0.0;
    $trade_date    = $data['trade_date'];

    // ✅ Default broker_id (until you implement multi-broker support)
    $broker_id = 1;

    // 🚀 Insert
    $db = new Database('development');
    $pdo = $db->getConnection();

    $sql = "
        INSERT INTO order_transaction 
        (broker_id, instrument_id, order_type, quantity, price, fees, trade_date)
        VALUES (:broker_id, :instrument_id, :order_type, :quantity, :price, :fees, :trade_date)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':broker_id'     => $broker_id,
        ':instrument_id' => $instrument_id,
        ':order_type'    => $order_type,
        ':quantity'      => $quantity,
        ':price'         => $price,
        ':fees'          => $fees,
        ':trade_date'    => $trade_date,
    ]);

    echo json_encode([
        "status"  => "success",
        "message" => "Order successfully added",
        "id"      => $pdo->lastInsertId()
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}
