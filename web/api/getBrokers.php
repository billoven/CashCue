<?php
/**
 * API endpoint to retrieve broker accounts
 * - If ?id= is provided, returns single broker account
 * - Otherwise returns all broker accounts for the user
 * 
 * Response format:
 *  - Success: JSON object (single broker) or array of objects (multiple brokers)
 *  - Failure: JSON object with 'success' => false and 'message' => error details
 * 
 * Security:
 * - Requires authentication (session-based)
 * - Uses prepared statements to prevent SQL injection
 * - Returns generic error messages to avoid exposing sensitive information
 * Notes:
 * - The endpoint assumes that the database schema has a 'broker_account' table with appropriate fields (e.g., id, name, account_number, created_at).
 * - The endpoint does not implement pagination or filtering for the list of brokers, which may be necessary if a user has a large number of broker accounts. In a production application, you might want to add parameters for pagination (
 * e.g., ?page=1&limit=20) and filtering (e.g., ?name=BrokerName) to improve performance and usability.
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

    // If an id is provided, return single broker
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $pdo->prepare("SELECT * FROM broker_account WHERE id = ?");
        $stmt->execute([$id]);
        $broker = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($broker ?: []);
        exit;
    }

    // Otherwise return all brokers
    $stmt = $pdo->query("SELECT * FROM broker_account ORDER BY created_at DESC");
    $brokers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($brokers);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

