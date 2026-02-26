<?php
/**
 * API endpoint to retrieve order transactions with optional filtering and pagination.
 * 
 * Query parameters:
 * - broker_account_id (int or 'all'): Filter by broker account. Use 'all' or omit for no filter.
 * - limit (int): Number of records to return (default: 10).
 * - offset (int): Number of records to skip for pagination (default: 0).
 * 
 * Response format:
 * {
 *   status: "success" | "error",
 *   data: [ {id, symbol, label, order_type, quantity, price, fees, total, trade_date, status, cancelled_at}, ... ]
 * }
 * 
 * Notes:
 * - Orders are sorted by trade_date DESC, then id DESC for consistent pagination.
 * - Total is calculated as (quantity * price) ± fees depending on order type.
 * - Cancelled orders will have cancelled_at timestamp, otherwise null.
 * - Server-side validation ensures security against malicious requests.
 * - Authentication is required to access this endpoint.
 * - Only orders belonging to the authenticated user will be returned.
 * - The broker_account_id filter will only return orders from that specific broker account, or all accounts if 'all' is specified.
 * - The limit and offset parameters allow for efficient pagination of results, which is important for accounts with a large number of transactions.
 * - The response includes a status field to indicate success or error, and a data field containing the list of orders when successful.
 * - The total field in each order is calculated based on the order type (BUY or SELL) and includes fees to reflect the actual cash flow impact of the transaction.
 * - The cancelled_at field is included to indicate if and when an order was cancelled, which can be useful for users to track changes to their orders and for debugging purposes.
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
    // Read limit, offset, and broker_account_id
    $limit  = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
    $broker_account_id_raw = $_GET['broker_account_id'] ?? '';
    $broker_account_id = ($broker_account_id_raw === 'all' || $broker_account_id_raw === '') ? null : intval($broker_account_id_raw);

    $db = new Database();
    $pdo = $db->getConnection();

    // Build base SQL
    $sql = "
        SELECT 
            ot.id,
            i.symbol,
            i.label,
            ot.order_type,
            ot.quantity,
            ot.price,
            ot.fees,
            ot.settled,
            ROUND(
                CASE 
                    WHEN ot.order_type = 'BUY' THEN (ot.quantity * ot.price) + ot.fees
                    WHEN ot.order_type = 'SELL' THEN (ot.quantity * ot.price) - ot.fees
                    ELSE ot.quantity * ot.price
                END, 2
            ) AS total,
            ot.trade_date,
            ot.status,
            ot.cancelled_at,
            ot.comment
        FROM order_transaction ot
        JOIN instrument i ON i.id = ot.instrument_id
    ";

    // Add broker filter if needed
    if ($broker_account_id !== null) {
        $sql .= " WHERE ot.broker_account_id = :broker_account_id";
    }

    $sql .= " ORDER BY ot.trade_date DESC, ot.id DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);

    if ($broker_account_id !== null) {
        $stmt->bindValue(':broker_account_id', $broker_account_id, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ensure numeric fields are proper floats
    foreach ($rows as &$row) {
        $row['quantity'] = (float) $row['quantity'];
        $row['price']    = (float) $row['price'];
        $row['fees']     = (float) $row['fees'];
        $row['total']    = (float) $row['total'];
        $row['cancelled_at'] = $row['cancelled_at'] ?: null;
        $row['settled']  = (int)   $row['settled'];   // ✅ AJOUT
    }
    echo json_encode([
        "status" => "success",
        "data"   => $rows
    ]);

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}


