<?php
/**
 * This endpoint retrieves the historical realtime price data for a specific instrument for the current day. It accepts an instrument_id as a query parameter and returns a JSON response containing an array of price records, each with a price and captured_at timestamp. The data is ordered chronologically by the captured_at timestamp. This allows clients to display intraday price movements for the instrument.
 * 
 * Example request: GET /api/getInstrumentHistory.php?instrument_id=123
 * 
 * Example response:
 * {
 *   "status": "success",
 *   "count": 5,
 *   "data": [
 *     {"price": 100.5, "captured_at": "2024-06-01T09:30:00Z"},
 *     {"price": 101.0, "captured_at": "2024-06-01T10:00:00Z"},
 *     ...
 *   ]
 * }
 * 
 * Error responses will have a status of "error" and include a message describing the issue.
 */ 
header('Content-Type: application/json; charset=utf-8');

// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../includes/auth.php';

// include database connection class
require_once __DIR__ . '/../config/database.php';

if (!isset($_GET['instrument_id'])) {
    echo json_encode(["status" => "error", "message" => "instrument_id required"]);
    exit;
}

try {
    $db = new Database('production');           // ← créer une instance
    $pdo = $db->getConnection();    // ← appeler la méthode
    $instrument_id = (int) $_GET['instrument_id'];

    // Select all realtime prices for today, ordered by timestamp
    $sql = "
        SELECT price, captured_at
        FROM realtime_price
        WHERE instrument_id = :instrument_id
          AND DATE(captured_at) = CURDATE()
        ORDER BY captured_at ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(["instrument_id" => $instrument_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "count" => count($rows),
        "data" => $rows
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
