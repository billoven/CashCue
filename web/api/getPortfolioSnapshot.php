<?php
/**
 * Get portfolio snapshots
 * 
 * Returns a list of portfolio snapshots with the following structure:
 * {
 *   status: "success",
 *   count: 10,
 *   data: [
 *     {
 *       snapshot_date: "2024-01-31",
 *       total_value: 10000.00,
 *       invested_value: 8000.00,
 *       cash_balance: 2000.00
 *     },
 *     ...
 *   ]
 * }
 * 
 * Notes:
 * - snapshot_date is the date of the snapshot (e.g., end of month)
 * - total_value is the total portfolio value at that date (holdings + cash)
 * - invested_value is the total amount invested in holdings (sum of buy orders)
 * - cash_balance is the cash available in broker accounts at that date
 * 
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
    $db = new Database('production');           // ← créer une instance
    $pdo = $db->getConnection();    // ← appeler la méthode

    $sql = "
        SELECT 
            snapshot_date,
            total_value,
            invested_value,
            cash_balance
        FROM portfolio_snapshot
        ORDER BY snapshot_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "count" => count($rows), "data" => $rows]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
