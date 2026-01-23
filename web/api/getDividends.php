<?php
/**
 * getDividends.php
 *
 * Returns dividends with full lifecycle information:
 * - status (ACTIVE / CANCELLED)
 * - cancelled_at
 *
 * Supports filters:
 * - id
 * - broker_account_id
 * - instrument_id
 * - date range
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';

try {
    $db  = new Database('production');
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
        SELECT
            d.id,
            d.broker_account_id,
            b.name AS broker_name,
            d.instrument_id,
            i.symbol,
            i.label,
            d.payment_date,
            d.gross_amount,
            d.taxes_withheld,
            d.amount,
            d.currency,
            d.status,
            d.cancelled_at,
            d.created_at
        FROM dividend d
        LEFT JOIN broker_account b ON d.broker_account_id = b.id
        LEFT JOIN instrument i      ON d.instrument_id = i.id
        WHERE 1 = 1
    ";

    $params = [];

    // --------------------------------------------------
    // Optional filters
    // --------------------------------------------------

    // Single dividend (used for edit / view)
    if (!empty($_GET['id'])) {
        $sql .= " AND d.id = :id";
        $params[':id'] = (int) $_GET['id'];
    }

    if (!empty($_GET['broker_account_id'])) {
        $sql .= " AND d.broker_account_id = :broker_account_id";
        $params[':broker_account_id'] = (int) $_GET['broker_account_id'];
    }

    if (!empty($_GET['instrument_id'])) {
        $sql .= " AND d.instrument_id = :instrument_id";
        $params[':instrument_id'] = (int) $_GET['instrument_id'];
    }

    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $sql .= " AND d.payment_date BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $_GET['start_date'];
        $params[':end_date']   = $_GET['end_date'];
    }

    // --------------------------------------------------
    // Ordering
    // --------------------------------------------------
    $sql .= " ORDER BY d.payment_date DESC, d.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $dividends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data'    => $dividends
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
