<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('development');
    $pdo = $db->getConnection();

    // Determine range (in days)
    $range = isset($_GET['range']) && $_GET['range'] !== 'all'
        ? (int) $_GET['range']
        : null;

    // ✅ Adjust WHERE clause if a range is provided
    $whereClause = $range ? "WHERE ps.date >= CURDATE() - INTERVAL $range DAY" : "";

    // ✅ Main query: join invested and portfolio data by date
    $sql = "
        SELECT 
            COALESCE(ps.date, inv.date) AS date,
            ROUND(COALESCE(inv.invested, 0), 2) AS invested,
            ROUND(COALESCE(ps.total_value, 0), 2) AS portfolio
        FROM (
            SELECT 
                DATE(trade_date) AS date,
                SUM(CASE WHEN order_type = 'BUY' THEN quantity * price 
                         WHEN order_type = 'SELL' THEN -quantity * price 
                         ELSE 0 END) AS invested
            FROM order_transaction
            GROUP BY DATE(trade_date)
        ) inv
        LEFT JOIN portfolio_snapshot ps ON ps.date = inv.date
        $whereClause

        UNION

        SELECT 
            ps.date,
            0 AS invested,
            ROUND(ps.total_value, 2) AS portfolio
        FROM portfolio_snapshot ps
        $whereClause
        ORDER BY date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo json_encode([
            "status" => "success",
            "data" => [],
            "message" => "No portfolio history found"
        ]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "count" => count($rows),
        "data" => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}
