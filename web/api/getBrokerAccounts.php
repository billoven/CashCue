<?php
/**
 * ------------------------------------------------------------
 * Endpoint: GET /api/getBrokerAccounts.php
 * ------------------------------------------------------------
 * Description:
 *   Returns broker accounts accessible to the authenticated user.
 *
 * Access rules:
 *   - SUPER_ADMIN → all broker accounts
 *   - Other users → only broker accounts linked via user_broker_account
 *
 * Response:
 *   [
 *     {
 *       "id": 1,
 *       "label": "Broker A Cash Account",
 *       "account_number": "12345678",
 *       "currency": "USD"
 *     }
 *   ]
 * ------------------------------------------------------------
 */

header('Content-Type: application/json; charset=utf-8');

// CashCue app context
define('CASHCUE_APP', true);

// Authentication & session guard
require_once __DIR__ . '/../includes/auth.php';

// Database
require_once __DIR__ . '/../config/database.php';

try {

    $db  = new Database('production');
    $pdo = $db->getConnection();

    // ------------------------------------------------------------
    // SUPER ADMIN → all accounts
    // ------------------------------------------------------------
    if (isSuperAdmin()) {

        $sql = "
            SELECT 
                id,
                CONCAT(name, ' ', account_type) AS label,
                account_number,
                currency
            FROM broker_account
            ORDER BY name
        ";

        $stmt = $pdo->query($sql);

    } 
    // ------------------------------------------------------------
    // STANDARD USER → only linked accounts
    // ------------------------------------------------------------
    else {

        $sql = "
            SELECT 
                ba.id,
                CONCAT(ba.name, ' ', ba.account_type) AS label,
                ba.account_number,
                ba.currency
            FROM broker_account ba
            INNER JOIN user_broker_account uba
                ON uba.broker_account_id = ba.id
            WHERE uba.user_id = :user_id
            ORDER BY ba.name
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $currentUserId
        ]);
    }

    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($accounts);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error'   => 'Unable to retrieve broker accounts'
    ]);

}
