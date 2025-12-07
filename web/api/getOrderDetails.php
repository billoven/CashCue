<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("Missing or invalid order ID");
    }

    $id = (int) $_GET['id'];

    $db = new Database('production');
    $pdo = $db->getConnection();

    $sql = "
        SELECT 
            o.id,
            o.instrument_id,
            i.symbol,
            i.label,
            o.order_type,
            o.quantity,
            o.price,
            o.fees,
            o.trade_date,
            o.settled
        FROM order_transaction o
        JOIN instrument i ON o.instrument_id = i.id
        WHERE o.id = :id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found");
    }

    echo json_encode([
        "status" => "success",
        "data"   => $order
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}
