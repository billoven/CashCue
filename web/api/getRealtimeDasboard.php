<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../classes/Instrument.php';
require_once __DIR__ . '/../classes/PortfolioSnapshot.php';
require_once __DIR__ . '/../config/database.php';

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
