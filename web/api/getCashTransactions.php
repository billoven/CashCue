<?php
/**
 * API: getCashTransactions.php
 *
 * Purpose:
 * - Retrieve cash transactions for listing (by broker_account_id)
 * - Retrieve a single cash transaction for edition (by transaction id)
 *
 * Priority rule:
 * - If "id" is provided, it takes precedence over broker_account_id
 *
 * Accepted GET parameters:
 * - id                  (optional) : cash_transaction.id (edition use case)
 * - broker_account_id   (optional) : numeric ID or 'all' (listing use case)
 * - from                (optional) : YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
 * - to                  (optional) : YYYY-MM-DD or YYYY-MM-DD HH:MM:SS
 * - type                (optional) : BUY, SELL, DIVIDEND, etc.
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

try {
    // --------------------------------------------------
    // Input parameters
    // --------------------------------------------------
    $transaction_id    = $_GET['id'] ?? null;
    $broker_account_id = $_GET['broker_account_id'] ?? null;

    $from = $_GET['from'] ?? null;
    $to   = $_GET['to']   ?? null;
    $type = $_GET['type'] ?? null;

    // At least one primary selector must be provided
    if (!$transaction_id && !$broker_account_id) {
        throw new Exception('Missing id or broker_account_id');
    }

    // --------------------------------------------------
    // Database connection
    // --------------------------------------------------
    $db  = new Database();
    $pdo = $db->getConnection();

    // --------------------------------------------------
    // Base SQL query
    // --------------------------------------------------
    $sql = "
        SELECT
            id,
            broker_account_id,
            date,
            type,
            amount,
            reference_id,
            comment
        FROM cash_transaction
        WHERE 1=1
    ";

    $params = [];

    // --------------------------------------------------
    // Edition mode (transaction ID has priority)
    // --------------------------------------------------
    if ($transaction_id) {
        $transaction_id = (int)$transaction_id;

        if ($transaction_id <= 0) {
            throw new Exception('Invalid transaction id');
        }

        $sql .= " AND id = :id";
        $params[':id'] = $transaction_id;
    }
    // --------------------------------------------------
    // Listing mode (filtered by broker account)
    // --------------------------------------------------
    elseif ($broker_account_id !== 'all') {
        $broker_account_id = (int)$broker_account_id;

        if ($broker_account_id <= 0) {
            throw new Exception('Invalid broker_account_id');
        }

        $sql .= " AND broker_account_id = :broker_account_id";
        $params[':broker_account_id'] = $broker_account_id;
    }

    // --------------------------------------------------
    // Optional secondary filters
    // --------------------------------------------------
    if ($from) {
        $sql .= " AND date >= :from";
        $params[':from'] = $from;
    }

    if ($to) {
        $sql .= " AND date <= :to";
        $params[':to'] = $to;
    }

    if ($type) {
        $sql .= " AND type = :type";
        $params[':type'] = $type;
    }

    // --------------------------------------------------
    // Sorting (consistent for list & edition)
    // --------------------------------------------------
    $sql .= " ORDER BY date DESC, id DESC";

    // --------------------------------------------------
    // Execute query
    // --------------------------------------------------
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // Successful response
    // --------------------------------------------------
    echo json_encode([
        'success' => true,
        'count'   => count($rows),
        'data'    => $rows
    ]);

} catch (Exception $e) {

    // --------------------------------------------------
    // Error handling
    // --------------------------------------------------
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}


