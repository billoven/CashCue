<?php
/**
 * API endpoint to update a broker account
 * Expects POST data with the following fields:
 * - id (required, integer)
 * - name (required, string)
 * - account_number (optional, string)
 * - account_type (optional, string, default: PEA)
 * - currency (optional, string, default: EUR)
 * - comment (required, string)
 *
 * Returns JSON response:
 * {
 *   success: boolean,
 *   message: string
 * }
 *
 * Validation:
 * - id must be a positive integer
 * - name cannot be empty
 * - comment cannot be empty
 *
 * Only the specified fields are updated. Other fields in the broker_account record remain unchanged.
 * The account_number field is optional, but if provided, it should be unique. This is enforced by
 * a UNIQUE constraint in the database schema, and any violation will result in an error response.
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

    // ==============================
    // Récupération des champs POST
    // ==============================
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $account_type = trim($_POST['account_type'] ?? 'PEA');
    $currency = strtoupper(trim($_POST['currency'] ?? 'EUR'));
    $comment = trim($_POST['comment'] ?? '');

    // ==============================
    // Validation
    // ==============================
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid broker ID.']);
        exit;
    }

    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Broker name cannot be empty.']);
        exit;
    }

    if ($comment === '') {
        echo json_encode(['success' => false, 'message' => 'Comment is required.']);
        exit;
    }

    // ==============================
    // Mise à jour du broker (seulement les champs autorisés)
    // ==============================
    $stmt = $pdo->prepare("
        UPDATE broker_account
        SET name = ?, 
            account_number = ?, 
            account_type = ?, 
            currency = ?, 
            comment = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $name,
        $account_number ?: null,
        $account_type,
        $currency,
        $comment,
        $id
    ]);

    echo json_encode(['success' => true, 'message' => 'Broker updated successfully.']);

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'message' => 'Account number already exists.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
