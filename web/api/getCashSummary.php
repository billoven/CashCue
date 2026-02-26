<?php
/**
 * getCashSummary.php
 *
 * Returns cash overview data:
 *  - current_balance (from cash_account.current_balance)
 *  - total_inflows  (SUM of positive transactions)
 *  - total_outflows (SUM of negative transactions, absolute value)
 *
 * IMPORTANT:
 * We do NOT recompute balance from initial_balance + SUM(transactions)
 * because current_balance is already maintained by the application logic.
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

    if (!isset($_GET['broker_account_id'])) {
        throw new Exception('Missing broker_account_id');
    }

    $broker_account_id = $_GET['broker_account_id'];

    $db  = new Database();
    $pdo = $db->getConnection();

    /*
    |--------------------------------------------------------------------------
    | CASE 1: ALL BROKERS
    |--------------------------------------------------------------------------
    */
    if ($broker_account_id === 'all') {

        $sql = "
            SELECT
                COALESCE(SUM(ca.current_balance), 0) AS total_balance,

                COALESCE(SUM(
                    CASE WHEN ct.amount > 0 THEN ct.amount ELSE 0 END
                ), 0) AS total_inflows,

                COALESCE(SUM(
                    CASE WHEN ct.amount < 0 THEN ABS(ct.amount) ELSE 0 END
                ), 0) AS total_outflows

            FROM cash_account ca
            LEFT JOIN cash_transaction ct
                ON ct.broker_account_id = ca.broker_account_id
        ";

        $stmt = $pdo->query($sql);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        $balance = (float)$row['total_balance'];

        $status  = $balance > 0.01
            ? 'positive'
            : ($balance < -0.01 ? 'negative' : 'neutral');

        echo json_encode([
            'broker_account_id' => 'all',
            'account_name'      => 'All brokers',
            'currency'          => 'EUR',
            'initial_balance'   => null,
            'current_balance'   => round($balance, 2),
            'total_inflows'     => round((float)$row['total_inflows'], 2),
            'total_outflows'    => round((float)$row['total_outflows'], 2),
            'status'            => $status
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CASE 2: SINGLE BROKER
    |--------------------------------------------------------------------------
    */

    $broker_account_id = (int)$broker_account_id;

    if ($broker_account_id <= 0) {
        throw new Exception('Invalid broker_account_id');
    }

    $sql = "
        SELECT
            ca.broker_account_id,
            ca.name,
            ca.initial_balance,
            ca.current_balance,

            COALESCE(SUM(
                CASE WHEN ct.amount > 0 THEN ct.amount ELSE 0 END
            ), 0) AS total_inflows,

            COALESCE(SUM(
                CASE WHEN ct.amount < 0 THEN ABS(ct.amount) ELSE 0 END
            ), 0) AS total_outflows

        FROM cash_account ca
        LEFT JOIN cash_transaction ct
            ON ct.broker_account_id = ca.broker_account_id
        WHERE ca.broker_account_id = :broker_account_id
        GROUP BY
            ca.broker_account_id,
            ca.name,
            ca.initial_balance,
            ca.current_balance
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['broker_account_id' => $broker_account_id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Cash account not found');
    }

    $balance = (float)$row['current_balance'];

    $status  = $balance > 0.01
        ? 'positive'
        : ($balance < -0.01 ? 'negative' : 'neutral');

    echo json_encode([
        'broker_account_id' => (int)$row['broker_account_id'],
        'account_name'      => $row['name'],
        'currency'          => 'EUR',
        'initial_balance'   => (float)$row['initial_balance'], // informational only
        'current_balance'   => round($balance, 2),
        'total_inflows'     => round((float)$row['total_inflows'], 2),
        'total_outflows'    => round((float)$row['total_outflows'], 2),
        'status'            => $status
    ]);

} catch (Throwable $e) {

    http_response_code(400);

    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
