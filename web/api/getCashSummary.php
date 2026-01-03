<?php
/**
 * getCashSummary.php
 *
 * Returns the current cash balance for a broker account.
 * Balance = initial_balance + SUM(cash_transaction.amount)
 */

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['broker_id'])) {
        throw new Exception('Missing broker_id');
    }

    $broker_id = $_GET['broker_id'];

    $db  = new Database();
    $pdo = $db->getConnection();

    if ($broker_id === 'all') {
        // Somme sur tous les brokers
        $sql = "
            SELECT
                COALESCE(SUM(ca.initial_balance + COALESCE(ct.amount,0)), 0) AS total_balance
            FROM cash_account ca
            LEFT JOIN cash_transaction ct ON ct.broker_account_id = ca.broker_id
        ";
        $stmt = $pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $balance = (float)$row['total_balance'];
        $status  = $balance > 0.01 ? 'positive' : ($balance < -0.01 ? 'negative' : 'neutral');

        echo json_encode([
            'broker_id'       => 'all',
            'account_name'    => 'All brokers',
            'currency'        => 'EUR',
            'initial_balance' => null,
            'current_balance' => round($balance, 2),
            'status'          => $status
        ]);
    } else {
        $broker_id = (int)$broker_id;
        if ($broker_id <= 0) {
            throw new Exception('Invalid broker_id');
        }

        $sql = "
            SELECT
                ca.broker_id,
                ca.name,
                ca.initial_balance,
                COALESCE(SUM(ct.amount), 0) AS movements_sum
            FROM cash_account ca
            LEFT JOIN cash_transaction ct
                ON ct.broker_account_id = ca.broker_id
            WHERE ca.broker_id = :broker_id
            GROUP BY
                ca.broker_id,
                ca.name,
                ca.initial_balance
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['broker_id' => $broker_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Cash account not found');
        }

        $balance = (float)$row['initial_balance'] + (float)$row['movements_sum'];
        $status  = $balance > 0.01 ? 'positive' : ($balance < -0.01 ? 'negative' : 'neutral');

        echo json_encode([
            'broker_id'       => (int)$row['broker_id'],
            'account_name'    => $row['name'],
            'currency'        => 'EUR',
            'initial_balance' => (float)$row['initial_balance'],
            'current_balance' => round($balance, 2),
            'status'          => $status
        ]);
    }

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
