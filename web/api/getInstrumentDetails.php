<?php
/**
 * getInstrumentDetails.php
 * -----------------------
 * Returns full details of a financial instrument.
 *
 * Includes:
 * - Core instrument attributes
 * - Current lifecycle status
 * - Allowed status transitions
 * - Operability indicators for UI usage
 */

header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => "Missing instrument ID"
    ]);
    exit;
}

try {
    // --------------------------------------------------
    // Database connection
    // --------------------------------------------------
    $db  = new Database('production');
    $pdo = $db->getConnection();

    // --------------------------------------------------
    // Fetch instrument
    // --------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT
            id,
            symbol,
            label,
            isin,
            type,
            currency,
            status
        FROM instrument
        WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode([
            "status"  => "error",
            "message" => "Instrument not found"
        ]);
        exit;
    }

    // --------------------------------------------------
    // Status transition rules (must mirror updateInstrument.php)
    // --------------------------------------------------
    $statusTransitions = [
        'ACTIVE'     => ['INACTIVE', 'SUSPENDED', 'DELISTED'],
        'INACTIVE'   => ['ACTIVE'],
        'SUSPENDED'  => ['ACTIVE'],
        'DELISTED'   => ['ARCHIVED'],
        'ARCHIVED'   => []
    ];

    $currentStatus = $row['status'];

    // --------------------------------------------------
    // Derive UI helpers
    // --------------------------------------------------
    $row['allowed_transitions'] = $statusTransitions[$currentStatus] ?? [];
    $row['is_operable'] = in_array($currentStatus, ['ACTIVE'], true);
    $row['is_readonly'] = in_array($currentStatus, ['ARCHIVED'], true);

    echo json_encode([
        "status" => "success",
        "data"   => $row
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}
