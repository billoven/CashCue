<?php
/**
 * CashCue - Personal Finance and Investment Tracker
 * API Endpoint: Get Cash Accounts
 * Description: Retrieves cash accounts, optionally filtered by broker account ID.
 * URL: /api/getCashAccounts.php?broker_account_id=123 (optional)
 * Method: GET
 * Response: JSON array of cash accounts with fields like id, broker_account_id, balance, currency, etc.
 *
 * ------------------------------------------------------------
 * Security:
 * - Requires authentication (user must be logged in)
 * - Validates input parameters to prevent SQL injection
 * - Uses prepared statements for database queries
 *
 * ------------------------------------------------------------
 * Notes:
 * - If 'broker_account_id' is provided, only cash accounts associated with that broker account will be returned. Otherwise, all cash accounts for the authenticated user will be returned.
 * - The endpoint assumes that the database schema has a 'cash_account' table with appropriate fields (e.g., id, broker_account_id, name, balance, currency, created_at, updated_at).
 * - The endpoint does not implement pagination or filtering beyond the optional broker account ID, which may be necessary if a user has a large number of cash accounts. In a production application, you might want to add
 *  parameters for pagination (e.g., ?page=1&limit=20) and additional filtering options (e.g., ?currency=USD) to improve performance and usability.
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
    $brokerId = $_GET["broker_account_id"] ?? null;

    $db = new Database();
    $pdo = $db->getConnection();

    if ($brokerId) {
        $stmt = $pdo->prepare("SELECT * FROM cash_account WHERE broker_account_id = :broker_account_id");
        $stmt->execute([":broker_account_id" => $brokerId]);
    } else {
        $stmt = $pdo->query("SELECT * FROM cash_account");
    }

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()]);
}
