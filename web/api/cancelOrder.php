<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['id'])) throw new Exception('Missing order id');

    $order_id = (int)$_GET['id'];
    $db = new Database();
    $pdo = $db->getConnection();

    $pdo->beginTransaction();

    // 1️⃣ Récupérer l'ordre actif
    $sel = $pdo->prepare("
        SELECT id, broker_id, order_type, quantity, price, fees, settled, status 
        FROM order_transaction 
        WHERE id = :id 
        LIMIT 1
    ");
    $sel->execute([':id' => $order_id]);
    $order = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');
    if ($order['status'] !== 'ACTIVE') throw new Exception('Order is already cancelled');

    $broker_id = (int)$order['broker_id'];
    $amount = (float)$order['quantity'] * (float)$order['price'] + (float)$order['fees'];

    // 2️⃣ Déterminer l'effet cash inverse
    if ($order['order_type'] === 'BUY') {
        $reversal_amount = $amount; // BUY original: cash decreases, reversal: cash increases
    } elseif ($order['order_type'] === 'SELL') {
        $reversal_amount = -$amount; // SELL original: cash increases, reversal: cash decreases
    } else {
        throw new Exception('Unknown order type');
    }

    // 3️⃣ Marquer l'ordre comme CANCELLED
    $updOrder = $pdo->prepare("
        UPDATE order_transaction
        SET status = 'CANCELLED', cancelled_at = NOW()
        WHERE id = :id
    ");
    $updOrder->execute([':id' => $order_id]);

    // 4️⃣ Créer le cash reversal
    $insCash = $pdo->prepare("
        INSERT INTO cash_transaction
        (broker_account_id, date, amount, type, reference_id, comment)
        VALUES (:broker_id, NOW(), :amount, :type, :ref, :comment)
    ");
    $insCash->execute([
        ':broker_id' => $broker_id,
        ':amount' => $reversal_amount,
        ':type' => $order['order_type'],
        ':ref' => $order_id,
        ':comment' => "Reversal of {$order['order_type']} order #$order_id"
    ]);

    // 5️⃣ Mettre à jour le solde cash du broker
    $sumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount),0) AS sum_amount 
        FROM cash_transaction 
        WHERE broker_account_id = :broker_id
    ");
    $sumStmt->execute([':broker_id' => $broker_id]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);

    $updBalance = $pdo->prepare("
        UPDATE cash_account 
        SET current_balance = :bal, updated_at = NOW() 
        WHERE broker_id = :broker_id
    ");
    $updBalance->execute([
        ':bal' => $sumRow['sum_amount'],
        ':broker_id' => $broker_id
    ]);

    $pdo->commit();

    // ✅ Retour JSON
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'new_status' => 'CANCELLED',
        'cash_reversal_amount' => $reversal_amount,
        'broker_balance' => $sumRow['sum_amount']
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
