<?php
/**
 * getInstruments.php
 * ------------------
 * API endpoint returning the list of financial instruments.
 *
 * Design principles:
 * - No physical deletion: instruments are managed via lifecycle status
 * - This endpoint is read-only and UI-agnostic
 * - Status is exposed so the frontend can decide how to display or filter
 *
 * Returned fields:
 * - id
 * - symbol
 * - label
 * - isin
 * - type
 * - currency
 * - status   (active, inactive, draft, archived)
 */

header("Content-Type: application/json");

require_once __DIR__ . '/../config/database.php';

try {
    $db  = new Database('production');
    $pdo = $db->getConnection();

    $sql = "
        SELECT
            id,
            symbol,
            label,
            isin,
            type,
            currency,
            status
        FROM instrument
        ORDER BY label ASC
    ";

    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "count"  => count($rows),
        "data"   => $rows
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        "status"  => "error",
        "message" => "Failed to load instruments",
        "detail"  => $e->getMessage()
    ]);
}
