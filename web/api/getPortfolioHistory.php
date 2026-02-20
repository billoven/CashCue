<?php
/**
 * getPortfolioHistory.php
 * ------------------------------------------------------------
 * Returns historical portfolio data:
 *   - daily invested capital (BUY only)
 *   - cumulative invested capital
 *   - portfolio valuation from portfolio_snapshot
 *
 * PARAMETERS:
 *   - range:
 *       * integer (e.g., 30) → last N days
 *       * "all"              → full available history
 *
 *   - broker_account_id:
 *       * integer ID         → single broker
 *       * "all"              → aggregate all brokers
 *
 * BUSINESS RULES:
 *   - Only executed trades count:
 *       status  = 'ACTIVE'
 *       settled = 1
 *
 *   - CANCELLED or unsettled orders are excluded
 *
 * DESIGN NOTES:
 *   - Avoids unsafe casting of 'all' to integer
 *   - Uses dynamic SQL filters only when necessary
 *   - Keeps SQL readable and maintainable
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db  = new Database('production');
    $pdo = $db->getConnection();

    // --------------------------------------------------
    // 1) Parameter Handling
    // --------------------------------------------------

    // ---- Range handling ----
    $rangeParam = $_GET['range'] ?? '30';

    if (strtolower($rangeParam) === 'all') {
        $daysLimit = null; // no date restriction
    } else {
        $daysLimit = max(1, (int)$rangeParam);
    }

    // ---- Broker handling ----
    $brokerParam = $_GET['broker_account_id'] ?? 'all';
    $isAllBroker = (strtolower($brokerParam) === 'all' || $brokerParam === '');

    // --------------------------------------------------
    // 2) Dynamic SQL Filters
    // --------------------------------------------------

    $orderBrokerFilter    = '';
    $snapshotBrokerFilter = '';
    $dateFilter           = '';

    if (!$isAllBroker) {
        $orderBrokerFilter    = 'AND ot.broker_account_id = :brokerId';
        $snapshotBrokerFilter = 'AND ps.broker_account_id = :brokerId';
    }

    if ($daysLimit !== null) {
        $dateFilter = '
            AND COALESCE(ps.date, di.date)
                >= DATE_SUB(CURDATE(), INTERVAL :daysLimit DAY)
        ';
    }

    // --------------------------------------------------
    // 3) SQL Query
    // --------------------------------------------------
    // Explanation:
    //  - daily_investments: aggregates BUY trades per day
    //  - merged: aligns investments and portfolio snapshots by date
    //  - final SELECT computes cumulative invested capital

    $sql = "
        WITH daily_investments AS (
            SELECT
                DATE(ot.trade_date) AS date,
                ROUND(
                    SUM(
                        CASE
                            WHEN ot.order_type = 'BUY'
                                 AND ot.status = 'ACTIVE'
                                 AND ot.settled = 1
                            THEN ot.quantity * ot.price
                            ELSE 0
                        END
                    ),
                2) AS daily_invested
            FROM order_transaction ot
            WHERE 1=1
                $orderBrokerFilter
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
            WHERE 1=1
                $snapshotBrokerFilter
                $dateFilter
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

    // --------------------------------------------------
    // 4) Prepare & Bind
    // --------------------------------------------------

    $stmt = $pdo->prepare($sql);

    if (!$isAllBroker) {
        $stmt->bindValue(':brokerId', (int)$brokerParam, PDO::PARAM_INT);
    }

    if ($daysLimit !== null) {
        $stmt->bindValue(':daysLimit', $daysLimit, PDO::PARAM_INT);
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // 5) Response
    // --------------------------------------------------

    echo json_encode([
        'status' => 'success',
        'range'  => $rangeParam,
        'broker' => $isAllBroker ? 'ALL' : (int)$brokerParam,
        'count'  => count($rows),
        'data'   => $rows
    ]);

} catch (Exception $e) {

    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}