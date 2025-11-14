<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {

    // Read limit and offset from GET or POST
    $limit  = isset($_GET['limit']) ? intval($_GET['limit']) : 5;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    $db = new Database('development');
    $pdo = $db->getConnection();

    $sql = "
        SELECT 
            ot.id,
            i.symbol,
            i.label,
            ot.order_type,
            ot.quantity,
            ot.price,
            ot.fees,
            ROUND(
                CASE 
                    WHEN ot.order_type = 'BUY' THEN (ot.quantity * ot.price) + ot.fees
                    WHEN ot.order_type = 'SELL' THEN (ot.quantity * ot.price) - ot.fees
                    ELSE ot.quantity * ot.price
                END, 2
            ) AS total,
            ot.trade_date,
            CONCAT(b.name, '_', b.account_type) AS broker_full_name
        FROM order_transaction ot
        JOIN instrument i ON i.id = ot.instrument_id
        JOIN broker_account b ON ot.broker_id = b.id
        ORDER BY ot.trade_date DESC, ot.id DESC
        LIMIT $limit OFFSET $offset
    ";
    

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ✅ Ensure numeric fields are proper floats
    foreach ($rows as &$row) {
        $row['quantity']    = (float) $row['quantity'];
        $row['price']       = (float) $row['price'];
        $row['fees']        = (float) $row['fees'];
        $row['total_cost']  = (float) $row['total_cost'];
    }

    echo json_encode([
        "status" => "success",
        "data"   => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}


