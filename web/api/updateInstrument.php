<?php
/**
 * updateInstrument.php
 * --------------------
 * Updates an existing financial instrument.
 *
 * Responsibilities:
 * - Update instrument core attributes (symbol, isin, label, type, currency)
 * - Optionally update instrument status
 * - Validate status transitions according to business rules
 * - Enforce read-only fields based on lifecycle status
 *
 * No physical deletion is allowed.
 * Instrument lifecycle is fully driven by status changes.
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
    // --------------------------------------------------
    // Database connection
    // --------------------------------------------------
    $db  = new Database('production');
    $pdo = $db->getConnection();

    // --------------------------------------------------
    // Input validation
    // --------------------------------------------------
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || empty($data['id'])) {
        throw new Exception("Invalid or missing instrument ID.");
    }

    $instrumentId = (int)$data['id'];

    // --------------------------------------------------
    // Fetch current instrument
    // --------------------------------------------------
    $stmt = $pdo->prepare("SELECT * FROM instrument WHERE id = :id");
    $stmt->execute([':id' => $instrumentId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        throw new Exception("Instrument not found.");
    }

    $currentStatus = $current['status'];
    $newStatus     = $data['status'] ?? $currentStatus;

    // --------------------------------------------------
    // Status transition rules
    // --------------------------------------------------
    $allowedTransitions = [
        'ACTIVE'     => ['INACTIVE', 'SUSPENDED', 'DELISTED'],
        'INACTIVE'   => ['ACTIVE'],
        'SUSPENDED'  => ['ACTIVE'],
        'DELISTED'   => ['ARCHIVED'],
        'ARCHIVED'   => []
    ];

    if ($newStatus !== $currentStatus) {
        if (!isset($allowedTransitions[$currentStatus]) ||
            !in_array($newStatus, $allowedTransitions[$currentStatus], true)) {
            throw new Exception("Invalid status transition: {$currentStatus} → {$newStatus}");
        }
    }

    // --------------------------------------------------
    // Read-only fields by status
    // --------------------------------------------------
    $readOnlyFieldsByStatus = [
        'DELISTED' => ['symbol', 'isin', 'type', 'currency'],
        'ARCHIVED' => ['symbol', 'isin', 'type', 'currency', 'label']
    ];

    foreach ($readOnlyFieldsByStatus[$currentStatus] ?? [] as $f) {
        if (isset($data[$f]) && $data[$f] !== ($current[$f] ?? null)) {
            throw new Exception("Cannot modify '{$f}' when status is {$currentStatus}");
        }
    }

    // --------------------------------------------------
    // Build dynamic UPDATE query
    // --------------------------------------------------
    $fields = [
        'symbol'   => $data['symbol']   ?? null,
        'isin'     => $data['isin']     ?? null,
        'label'    => $data['label']    ?? null,
        'type'     => $data['type']     ?? null,
        'currency' => $data['currency'] ?? null,
        'status'   => $newStatus
    ];

    $setParts = [];
    $params   = [':id' => $instrumentId];

    foreach ($fields as $column => $value) {
        if ($value !== null) {
            $setParts[]           = "{$column} = :{$column}";
            $params[":{$column}"] = $value;
        }
    }

    if (empty($setParts)) {
        throw new Exception("No fields to update.");
    }

    $sql = "UPDATE instrument SET " . implode(', ', $setParts) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        "status"          => "success",
        "message"         => "Instrument updated successfully",
        "previous_status" => $currentStatus,
        "current_status"  => $newStatus
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
    exit;
}
