<?php
/**
 * getHoldings.php
 * ----------------
 * Returns the current holdings per instrument for a given broker account or all accounts.
 * 
 * Features:
 * - Aggregates active and settled orders (BUY and SELL) per instrument.
 * - Computes total quantity, average buy price, last market price, current value, and unrealized P/L.
 * - Supports filtering by a single broker account or all accounts.
 * - Designed for clarity and easy debugging rather than single heavy SQL query.
 * 
 * GET parameters:
 * - broker_account_id : integer ID of broker account, or "ALL" for all accounts.
 * 
 * Response JSON:
 * {
 *   status: "success" | "error",
 *   count: integer,        // number of instruments returned
 *   data: [ {instrument data}, ... ] 
 * }
 */
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('production');
    $pdo = $db->getConnection();

    // -----------------------------
    // 1) Get broker parameter
    // -----------------------------
    $brokerId = $_GET['broker_account_id'] ?? 'ALL';
    $isAll = strtoupper($brokerId) === 'ALL';

    $brokerFilter = $isAll ? "" : "WHERE broker_account_id = :brokerId";

    // -----------------------------
    // 2) Fetch all transactions for relevant broker(s)
    // -----------------------------
    $sql = "
        SELECT 
            id,
            broker_account_id,
            instrument_id,
            order_type,
            quantity,
            price,
            trade_date,
            status,
            settled
        FROM order_transaction
        $brokerFilter
        ORDER BY instrument_id ASC, trade_date ASC
    ";

    $stmt = $pdo->prepare($sql);
    if (!$isAll) {
        $stmt->bindValue(':brokerId', intval($brokerId), PDO::PARAM_INT);
    }
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // -----------------------------
    // 3) Load last market price per instrument
    // -----------------------------
    $stmt = $pdo->query("
        SELECT instrument_id, price
        FROM realtime_price rp
        WHERE captured_at = (
            SELECT MAX(captured_at)
            FROM realtime_price rp2
            WHERE rp2.instrument_id = rp.instrument_id
        )
    ");
    $prices = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $prices[$row['instrument_id']] = $row['price'];
    }

    // -----------------------------
    // 4) Aggregate holdings per instrument
    // -----------------------------
    $holdings = [];
    foreach ($transactions as $tx) {
        if ($tx['status'] !== 'ACTIVE' || intval($tx['settled']) !== 1) continue;

        $instr = $tx['instrument_id'];
        if (!isset($holdings[$instr])) {
            $holdings[$instr] = [
                'instrument_id'   => $instr,
                'symbol'          => '', // to fill later
                'label'           => '',
                'total_qty'       => 0,
                'total_cost'      => 0.0,
            ];
        }

        if ($tx['order_type'] === 'BUY') {
            // Add quantity and weighted cost
            $holdings[$instr]['total_cost'] += $tx['quantity'] * $tx['price'];
            $holdings[$instr]['total_qty']  += $tx['quantity'];
        } elseif ($tx['order_type'] === 'SELL') {
            // Reduce quantity and total cost proportionally (weighted average)
            $avgCost = $holdings[$instr]['total_qty'] > 0 
                        ? $holdings[$instr]['total_cost'] / $holdings[$instr]['total_qty']
                        : 0;
            $holdings[$instr]['total_cost'] -= min($tx['quantity'], $holdings[$instr]['total_qty']) * $avgCost;
            $holdings[$instr]['total_qty']  -= $tx['quantity'];
        }
    }

    // -----------------------------
    // 5) Fetch instrument info (symbol, label)
    // -----------------------------
    if (!empty($holdings)) {
        $instrIds = implode(',', array_keys($holdings));
        $stmt = $pdo->query("SELECT id, symbol, label FROM instrument WHERE id IN ($instrIds)");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $holdings[$row['id']]['symbol'] = $row['symbol'];
            $holdings[$row['id']]['label']  = $row['label'];
        }
    }

    // -----------------------------
    // 6) Compute final metrics per holding
    // -----------------------------
    $result = [];
    foreach ($holdings as $h) {
        if ($h['total_qty'] <= 0) continue;

        $avg_buy_price = $h['total_cost'] / $h['total_qty'];
        $last_price    = $prices[$h['instrument_id']] ?? 0.0;
        $current_value = $h['total_qty'] * $last_price;
        $unrealized_pl = $current_value - $h['total_cost'];
        $unrealized_pl_pct = $avg_buy_price > 0
            ? ($unrealized_pl / $h['total_cost']) * 100
            : 0;

        $result[] = [
            'instrument_id'     => $h['instrument_id'],
            'symbol'            => $h['symbol'],
            'label'             => $h['label'],
            'total_qty'         => round($h['total_qty'], 4),
            'avg_buy_price'     => round($avg_buy_price, 4),
            'last_price'        => round($last_price, 4),
            'current_value'     => round($current_value, 2),
            'unrealized_pl'     => round($unrealized_pl, 2),
            'unrealized_pl_pct' => round($unrealized_pl_pct, 2),
        ];
    }

    echo json_encode([
        'status' => 'success',
        'count'  => count($result),
        'data'   => $result
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}