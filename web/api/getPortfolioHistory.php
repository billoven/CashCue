<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('production');
    $pdo = $db->getConnection();

    // -----------------------------
    // 1) Get and sanitize parameters
    // -----------------------------
    $daysLimit = isset($_GET['range']) ? max(1, intval($_GET['range'])) : 30;

    $brokerId = $_GET['broker_account_id'] ?? 'all';
    $isAll = ($brokerId === 'all' || $brokerId === '' || $brokerId === null);

    // -----------------------------
    // 2) Build WHERE filters
    // -----------------------------
    // $dateFilter = "date >= DATE_SUB(CURDATE(), INTERVAL :daysLimit DAY)";
    $dateFilter = "COALESCE(ps.date, di.date) >= DATE_SUB(CURDATE(), INTERVAL :daysLimit DAY)";


    // Filter for portfolio_snapshot
    $psFilter = $isAll 
        ? ""
        : "AND broker_id = :brokerId";

    // Filter for order_transaction
    $otFilter = $isAll
        ? ""
        : "WHERE broker_id = :brokerId";

    // -----------------------------
    // 3) SQL query with account filter
    // -----------------------------
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
            $otFilter
            GROUP BY DATE(trade_date)
        ),

        joined_data AS (
            SELECT 
                COALESCE(ps.date, di.date) AS date,
                ROUND(COALESCE(di.daily_invested, 0), 2) AS daily_invested,
                ROUND(COALESCE(ps.total_value, 0), 2) AS portfolio
            FROM portfolio_snapshot ps
            LEFT JOIN daily_investments di ON di.date = ps.date
            WHERE $dateFilter
            $psFilter
        )

        SELECT 
            date,
            daily_invested,
            ROUND(
                SUM(daily_invested) OVER (
                    ORDER BY date ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
                ), 2
            ) AS cum_invested,
            portfolio
        FROM joined_data
        ORDER BY date ASC;
    ";

    $stmt = $pdo->prepare($sql);

    // -----------------------------
    // 4) Bind parameters
    // -----------------------------
    $stmt->bindValue(':daysLimit', $daysLimit, PDO::PARAM_INT);

    if (!$isAll) {
        $stmt->bindValue(':brokerId', intval($brokerId), PDO::PARAM_INT);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // -----------------------------
    // 5) Output JSON
    // -----------------------------
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


