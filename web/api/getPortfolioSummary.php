<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('development');
    $pdo = $db->getConnection();

    // Optional range filtering
    $days = isset($_GET['range']) ? (int)$_GET['range'] : 30;
    $whereClause = "WHERE ps.date >= CURDATE() - INTERVAL $days DAY";

    // ---- SQL for daily investments + cumulative ----
    $sql = "
        WITH daily_investments AS (
            SELECT 
                DATE(trade_date) AS date,
                SUM(CASE 
                        WHEN order_type = 'BUY' THEN quantity * price
                        WHEN order_type = 'SELL' THEN -quantity * price
                        ELSE 0 
                    END) AS daily_invested
            FROM order_transaction
            GROUP BY DATE(trade_date)
        ),
        joined_data AS (
            SELECT 
                ps.date AS date,
                COALESCE(di.daily_invested, 0) AS daily_invested,
                ps.total_value AS portfolio
            FROM portfolio_snapshot ps
            LEFT JOIN daily_investments di ON ps.date = di.date

            UNION ALL

            SELECT 
                di.date,
                di.daily_invested,
                COALESCE(ps.total_value, 0)
            FROM daily_investments di
            LEFT JOIN portfolio_snapshot ps ON ps.date = di.date
        )
        SELECT 
            date,
            ROUND(SUM(daily_invested), 2) AS daily_invested,
            ROUND(SUM(SUM(daily_invested)) OVER (ORDER BY date ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW), 2) AS cum_invested,
            ROUND(MAX(portfolio), 2) AS portfolio
        FROM joined_data
        $whereClause
        GROUP BY date
        ORDER BY date ASC;
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}
?>
