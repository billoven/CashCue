<?php
/**
 * Get portfolio summary
 * 
 * Returns aggregated portfolio metrics such as:
 * - total_value
 * - invested_amount
 * - unrealized_pl
 * - realized_pl
 * - dividends_received
 * 
 * Response format:
 * {
 *   status: "success",
 *   data: {
 *     total_value: float,
 *     invested: float,
 *     unrealized_pl: float,
 *     realized_pl: float,
 *     dividends: float
 *   }
 * }
 */
header('Content-Type: application/json; charset=utf-8');

// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../includes/auth.php';

// include database connection class
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('production');
    $pdo = $db->getConnection();

    // 1. Total current value of portfolio
    $sql = "SELECT SUM(total_value) AS total_value 
            FROM portfolio_snapshot 
            WHERE date = (SELECT MAX(date) FROM portfolio_snapshot)";
    $totalValue = floatval($pdo->query($sql)->fetchColumn() ?? 0);

    // 2. Total invested amount
    $sql = "SELECT SUM(invested_amount) AS invested 
            FROM portfolio_snapshot 
            WHERE date = (SELECT MAX(date) FROM portfolio_snapshot)";
    $invested = floatval($pdo->query($sql)->fetchColumn() ?? 0);

    // 3. Total unrealized P/L
    $sql = "SELECT SUM(unrealized_pl) AS unrealized 
            FROM portfolio_snapshot
            WHERE date = (SELECT MAX(date) FROM portfolio_snapshot)";
    $unrealized = floatval($pdo->query($sql)->fetchColumn() ?? 0);

    // 4. Realized P/L — source = portfolio_snapshot
    $sql = "SELECT SUM(realized_pl) AS realized 
            FROM portfolio_snapshot 
            WHERE realized_pl IS NOT NULL";
    $realized = floatval($pdo->query($sql)->fetchColumn() ?? 0);

    // 5. Total dividends received
    $sql = "SELECT SUM(dividends_received) 
            FROM portfolio_snapshot
            WHERE date = (SELECT MAX(date) FROM portfolio_snapshot)";
    $dividends = floatval($pdo->query($sql)->fetchColumn() ?? 0);

    echo json_encode([
        "status" => "success",
        "data" => [
            "total_value"    => $totalValue,
            "invested"       => $invested,
            "unrealized_pl"  => $unrealized,
            "realized_pl"    => $realized,
            "dividends"      => $dividends
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
