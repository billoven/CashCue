<?php
/**
 * getPortfolioHistory.php
 *
 * Provides historical portfolio data:
 *  - daily invested amount (BUY orders only)
 *  - cumulative invested capital
 *  - portfolio value from snapshots
 *
 * Broker handling:
 *  - broker_account_id = ALL  → aggregate all brokers
 *  - broker_account_id = <id> → single broker
 *
 * IMPORTANT:
 *  - Uses Cashcue order model:
 *      status  = 'ACTIVE'
 *      settled = 1
 *  - CANCELLED or unsettled orders are excluded
 *  - Security / ownership checks are intentionally excluded for now
 */


header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db  = new Database('production');
    $pdo = $db->getConnection();

    // --------------------------------------------------
    // 1) Parameters
    // --------------------------------------------------
    $daysLimit = isset($_GET['range']) ? max(1, (int)$_GET['range']) : 30;

    $brokerId = $_GET['broker_account_id'] ?? 'all';
    $isAll    = ($brokerId === 'all' || $brokerId === '' || $brokerId === null);

    // --------------------------------------------------
    // 2) Filters
    // --------------------------------------------------
    $otFilter = $isAll ? '' : 'AND ot.broker_account_id = :brokerId';
    $psFilter = $isAll ? '' : 'AND ps.broker_account_id = :brokerId';

    // --------------------------------------------------
    // 3) SQL
    // --------------------------------------------------
    $sql = "
        WITH daily_investments AS (
        SELECT
            DATE(ot.trade_date) AS date,
            ROUND(
                SUM(
                    CASE
                        -- BUY only: real invested capital
                        WHEN ot.order_type = 'BUY'
                            AND ot.status = 'ACTIVE'
                            AND ot.settled = 1
                        THEN ot.quantity * ot.price
                        ELSE 0
                    END
                ),
            2) AS daily_invested
        FROM order_transaction ot
        WHERE
            1 = 1
            $otFilter
        GROUP BY DATE(ot.trade_date)
    ),

    merged AS (
        SELECT
            COALESCE(ps.date, di.date) AS date,
            COALESCE(di.daily_invested, 0) AS daily_invested,
            COALESCE(ps.total_value, 0) AS portfolio
        FROM portfolio_snapshot ps
        LEFT JOIN daily_investments di
            ON di.date = ps.date
        WHERE
            COALESCE(ps.date, di.date)
                >= DATE_SUB(CURDATE(), INTERVAL :daysLimit DAY)
            $psFilter
    )

    SELECT
        date,
        daily_invested,
        ROUND(
            SUM(daily_invested)
            OVER (ORDER BY date),
        2) AS cum_invested,
        ROUND(portfolio, 2) AS portfolio
    FROM merged
    ORDER BY date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':daysLimit', $daysLimit, PDO::PARAM_INT);

    if (!$isAll) {
        $stmt->bindValue(':brokerId', (int)$brokerId, PDO::PARAM_INT);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data'   => $rows
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}

