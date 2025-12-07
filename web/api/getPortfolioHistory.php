<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('production');
    $pdo = $db->getConnection();

    // ---- Build dynamic filter (optional range) ----
    $daysLimit = isset($_GET['range']) ? intval($_GET['range']) : 30;
    $whereClause = "WHERE ps.date >= DATE_SUB(CURDATE(), INTERVAL $daysLimit DAY)";

    // ---- Compute daily & cumulative invested ----
    $sql = "
        WITH daily_investments AS (
            SELECT 
                DATE(trade_date) AS date,
                SUM(
                    CASE 
                        WHEN order_type = 'BUY' THEN quantity * price
                        WHEN order_type = 'SELL' THEN -quantity * price
                        ELSE 0 
                    END
                ) AS daily_invested
            FROM order_transaction
            GROUP BY DATE(trade_date)
        ),
        joined_data AS (
            SELECT 
                COALESCE(ps.date, di.date) AS date,
                ROUND(COALESCE(di.daily_invested, 0), 2) AS daily_invested,
                ROUND(COALESCE(ps.total_value, 0), 2) AS portfolio
            FROM portfolio_snapshot ps
            LEFT JOIN daily_investments di ON di.date = ps.date
            $whereClause
        )
        SELECT 
            date,
            daily_invested,
            ROUND(SUM(daily_invested) OVER (ORDER BY date ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW), 2) AS cum_invested,
            portfolio
        FROM joined_data
        ORDER BY date ASC;
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data'   => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}

