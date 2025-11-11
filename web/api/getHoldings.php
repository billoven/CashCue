<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/database.php';

try {
    $$db = new Database('development');           // ← créer une instance
    $pdo = $db->getConnection();    // ← appeler la méthode

    // Aggregate all buy/sell orders per instrument
    $sql = "
        SELECT 
            i.id AS instrument_id,
            i.symbol,
            i.label,
            SUM(CASE WHEN o.order_type = 'BUY' THEN o.quantity ELSE -o.quantity END) AS total_qty,
            SUM(CASE WHEN o.order_type = 'BUY' THEN o.quantity * o.price ELSE 0 END) /
                NULLIF(SUM(CASE WHEN o.order_type = 'BUY' THEN o.quantity ELSE 0 END), 0) AS avg_buy_price,
            rp.price AS last_price,
            (SUM(CASE WHEN o.order_type = 'BUY' THEN o.quantity ELSE -o.quantity END) * rp.price) AS current_value,
            ((rp.price - (
                SUM(CASE WHEN o.order_type = 'BUY' THEN o.quantity * o.price ELSE 0 END) /
                NULLIF(SUM(CASE WHEN o.order_type = 'BUY' THEN o.quantity ELSE 0 END), 0)
            )) * SUM(CASE WHEN o.order_type = 'BUY' THEN o.quantity ELSE -o.quantity END)) AS unrealized_pl
        FROM order_transaction o
        JOIN instrument i ON i.id = o.instrument_id
        LEFT JOIN (
            SELECT instrument_id, price
            FROM realtime_price
            WHERE captured_at = (
                SELECT MAX(captured_at) 
                FROM realtime_price rp2 
                WHERE rp2.instrument_id = realtime_price.instrument_id
            )
        ) rp ON rp.instrument_id = i.id
        GROUP BY i.id, i.symbol, i.label, rp.price
        HAVING total_qty > 0
        ORDER BY i.label ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute percent P/L
    foreach ($rows as &$r) {
        $r['unrealized_pl_pct'] = $r['avg_buy_price'] > 0 
            ? round(($r['last_price'] - $r['avg_buy_price']) / $r['avg_buy_price'] * 100, 2)
            : 0;
        $r['avg_buy_price'] = round($r['avg_buy_price'], 4);
        $r['last_price'] = round($r['last_price'], 4);
        $r['current_value'] = round($r['current_value'], 2);
        $r['unrealized_pl'] = round($r['unrealized_pl'], 2);
    }

    echo json_encode(["status" => "success", "count" => count($rows), "data" => $rows]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
