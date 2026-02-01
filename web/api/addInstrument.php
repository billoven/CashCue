<?php
/**
 * addInstrument.php
 * -----------------
 * Adds a new financial instrument.
 *
 * Responsibilities:
 * - Set required fields (symbol, label, type, currency)
 * - Set initial status to ACTIVE
 * - Enforce data validation
 */

header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) {
        throw new Exception("Missing input data");
    }

    // Required fields
    $symbol   = strtoupper(trim($data['symbol'] ?? ''));
    $label    = trim($data['label'] ?? '');
    $type     = $data['type'] ?? 'stock';
    $currency = $data['currency'] ?? 'EUR';
    $isin     = $data['isin'] ?? null;

    if (!$symbol || !$label || !$type || !$currency) {
        throw new Exception("Missing required fields: symbol, label, type, or currency");
    }

    // --------------------------------------------------
    // Database connection
    // --------------------------------------------------
    $db  = new Database('production');
    $pdo = $db->getConnection();

    // --------------------------------------------------
    // Insert instrument with initial status ACTIVE
    // --------------------------------------------------
    $sql = "INSERT INTO instrument (symbol, label, isin, type, currency, status)
            VALUES (:symbol, :label, :isin, :type, :currency, :status)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':symbol'   => $symbol,
        ':label'    => $label,
        ':isin'     => $isin,
        ':type'     => $type,
        ':currency' => $currency,
        ':status'   => 'ACTIVE'
    ]);

    echo json_encode([
        "status"    => "success",
        "message"   => "Instrument added successfully",
        "insert_id" => $pdo->lastInsertId(),
        "current_status" => 'ACTIVE'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}
