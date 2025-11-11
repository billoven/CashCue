<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    // ✅ Use your Database class
   $db = new Database('development');
    $pdo = $db->getConnection();

    // ---- Compute portfolio totals ----
    $sql = "
        SELECT
            -- Total invested amount (sum of all BUY orders)
            ROUND(SUM(CASE WHEN o.order_type = 'BUY' THEN o.quantity * o.price ELSE 0 END), 2) AS invested_amount,

            -- Realized profit/loss (sum of SELL proceeds)
            ROUND(SUM(CASE WHEN o.order_type = 'SELL' THEN o.quantity * o.price ELSE 0 END), 2) AS realized_pl,

            -- Total dividends received
            (SELECT COALESCE(SUM(d.amount), 0) FROM dividend d) AS dividends,

            -- Latest portfolio snapshot totals
            (SELECT COALESCE(SUM(p.total_value), 0)
            FROM portfolio_snapshot p
            WHERE p.date = (SELECT MAX(date) FROM portfolio_snapshot)) AS total_value,

            (SELECT COALESCE(SUM(p.cash_balance), 0)
            FROM portfolio_snapshot p
            WHERE p.date = (SELECT MAX(date) FROM portfolio_snapshot)) AS cash_balance
        FROM order_transaction o
    ";


    $stmt = $pdo->query($sql);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallbacks
    if (!$row) {
        $row = [
            'invested_amount' => 0,
            'realized_pl' => 0,
            'dividends' => 0,
            'total_value' => 0,
            'cash_balance' => 0
        ];
    }

    // Compute Unrealized P/L
    $row['unrealized_pl'] = round(
        ($row['total_value'] - $row['invested_amount'] - $row['realized_pl']), 
        2
    );

    echo json_encode([
        'status' => 'success',
        'data'   => $row
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}
