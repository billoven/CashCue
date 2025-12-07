<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

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
