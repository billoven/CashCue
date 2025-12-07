<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('production');           // ← créer une instance
    $pdo = $db->getConnection();    // ← appeler la méthode
    error_log("Database connection established");

    // Get latest realtime price for each instrument
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
        ORDER BY i.label ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "count"  => count($rows),
        "data"   => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}


