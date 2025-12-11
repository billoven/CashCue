<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('production');
    $pdo = $db->getConnection();

    // Read broker_account_id (default = all)
    $brokerAccountId = $_GET['broker_account_id'] ?? 'all';
    $isAll = ($brokerAccountId === 'all');

    /**
     * Base SQL:
     * - Select latest realtime price per instrument
     * - Join daily_price for pct_change of the day
     * - If broker filter: restrict instruments via order_transaction
     */
    $sql = "
        SELECT 
            i.id AS instrument_id,
            i.symbol,
            i.label,
            rp.price,
            rp.currency,
            rp.captured_at,
            dp.pct_change
        FROM instrument i
        JOIN realtime_price rp
            ON rp.id = (
                SELECT rp2.id
                FROM realtime_price rp2
                WHERE rp2.instrument_id = i.id
                ORDER BY rp2.captured_at DESC
                LIMIT 1
            )
        LEFT JOIN daily_price dp 
            ON dp.instrument_id = i.id
           AND dp.date = CURDATE()
    ";

    // Apply filter for specific broker account
    if (!$isAll) {
        $sql .= "
            WHERE i.id IN (
                SELECT DISTINCT instrument_id
                FROM order_transaction
                WHERE broker_id = :broker_account_id
            )
        ";
    }

    $sql .= " ORDER BY i.label ASC";

    $stmt = $pdo->prepare($sql);

    if (!$isAll) {
        $stmt->bindValue(':broker_account_id', $brokerAccountId, PDO::PARAM_INT);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "count"  => count($rows),
        "data"   => $rows
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}



