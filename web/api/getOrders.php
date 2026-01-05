<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../config/database.php';

try {
    // Read limit, offset, and broker_account_id
    $limit  = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $broker_account_id_raw = $_GET['broker_account_id'] ?? '';
    $broker_account_id = ($broker_account_id_raw === 'all' || $broker_account_id_raw === '') ? null : intval($broker_account_id_raw);

    $db = new Database();
    $pdo = $db->getConnection();

    // Build base SQL
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
            ot.status,
            ot.cancelled_at,
            CONCAT(b.name, '_', b.account_type) AS broker_full_name
        FROM order_transaction ot
        JOIN instrument i ON i.id = ot.instrument_id
        JOIN broker_account b ON ot.broker_id = b.id
    ";

    // Add broker filter if needed
    if ($broker_account_id !== null) {
        $sql .= " WHERE ot.broker_id = :broker_id";
    }

    $sql .= " ORDER BY ot.trade_date DESC, ot.id DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    if ($broker_account_id !== null) {
        $stmt->bindValue(':broker_id', $broker_account_id, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure numeric fields are proper floats
    foreach ($rows as &$row) {
        $row['quantity'] = (float) $row['quantity'];
        $row['price']    = (float) $row['price'];
        $row['fees']     = (float) $row['fees'];
        $row['total']    = (float) $row['total'];
        $row['cancelled_at'] = $row['cancelled_at'] ?: null;
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


