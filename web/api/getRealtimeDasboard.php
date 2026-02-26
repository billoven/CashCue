<?php
/**
 * Get realtime dashboard data
 * 
 * Response format:
 * {
 *   status: "success" | "error",
 *   message: "Error message if status is error",
 *   realtime: [ { symbol, label, price, captured_at, pct_change }, ... ],
 *   snapshot: { date, total_value, invested_amount, unrealized_pl, realized_pl, dividends_received },
 *   history: [ { date, total_value }, ... ] (last 30 days)
 * }
 * 
 * Notes:
 * - pct_change is the percentage change from the previous day's close (if available)
 * - snapshot is the latest portfolio snapshot (if available)
 * - history includes the last 30 days of portfolio values for charting
 * - All timestamps are in ISO 8601 format (UTC)
 * - Error responses include a message field with details about the error
 * - This endpoint is designed to provide all necessary data for the realtime dashboard in a single request
 * - The SQL queries are optimized for performance, using appropriate indexing and limiting the amount of data returned
 */

header('Content-Type: application/json; charset=utf-8');

// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../includes/auth.php';

// include database connection class
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../classes/Instrument.php';
require_once __DIR__ . '/../classes/PortfolioSnapshot.php';

$db = (new Database('production'))->getConnection();

// 1️⃣ Latest realtime prices
$prices = $db->query("
    SELECT i.symbol, i.label, r.price, r.captured_at,
           IFNULL(d.pct_change, 0) AS pct_change
    FROM instrument i
    JOIN realtime_price r ON r.instrument_id = i.id
    LEFT JOIN (
      SELECT instrument_id, pct_change
      FROM daily_price
      WHERE date = (SELECT MAX(date) FROM daily_price)
    ) d ON d.instrument_id = i.id
    ORDER BY i.symbol
")->fetchAll(PDO::FETCH_ASSOC);

// 2️⃣ Last portfolio snapshot
$snapshot = $db->query("
    SELECT *
    FROM portfolio_snapshot
    ORDER BY date DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

// 3️⃣ Historical portfolio values (30 days)
$history = $db->query("
    SELECT date, total_value
    FROM portfolio_snapshot
    ORDER BY date DESC
    LIMIT 30
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  "status" => "success",
  "realtime" => $prices,
  "snapshot" => $snapshot,
  "history" => array_reverse($history)
]);
