<?php
// ----------------------
// addOrder.php (debug safe)
// ----------------------

// Activer le buffer pour éviter toute sortie prématurée
ob_start();

// Logging des erreurs
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/cashcue_php_errors.log');

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {
    // Lecture JSON
    $inputJson = file_get_contents('php://input');
    error_log("DEBUG: input JSON = $inputJson");

    $input = json_decode($inputJson, true);
    if (!$input || !is_array($input)) {
        throw new Exception('Missing or invalid JSON input');
    }

    // Champs requis
    $required = ['broker_account_id','instrument_id','order_type','quantity','price','trade_date'];
    foreach ($required as $r) {
        if (!isset($input[$r]) || $input[$r] === '') {
            throw new Exception("Missing required field: $r");
        }
    }

    // Parsing et validation
    $broker_account_id = (int)$input['broker_account_id'];
    $instrument_id = (int)$input['instrument_id'];
    $order_type = strtoupper($input['order_type']);
    if (!in_array($order_type, ['BUY','SELL'])) throw new Exception("Invalid order_type");
    $quantity = (float)$input['quantity'];
    $price = (float)$input['price'];
    $fees = isset($input['fees']) ? (float)$input['fees'] : 0.0;
    $trade_date = $input['trade_date'];
    $settled = isset($input['settled']) ? (int)$input['settled'] : 1;

    error_log("DEBUG: broker_account_id=$broker_account_id, instrument_id=$instrument_id, order_type=$order_type, quantity=$quantity, price=$price, fees=$fees, trade_date=$trade_date");

    // Connexion DB
    $db = new Database();
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->beginTransaction();
    error_log("DEBUG: transaction started");

    // ----------------------
    // Insert order_transaction
    // ----------------------
    $stmt = $pdo->prepare("
        INSERT INTO order_transaction
        (
        broker_account_id,
        instrument_id,
        order_type,
        quantity,
        price,
        fees,
        trade_date,
        settled,
        status,
        cancelled_at
        )
        VALUES
        (
        :broker_account_id,
        :instrument_id,
        :order_type,
        :quantity,
        :price,
        :fees,
        :trade_date,
        :settled,
        :status,
        :cancelled_at
        )
    ");

    $stmt->execute([
        ':broker_account_id' => $broker_account_id,
        ':instrument_id'     => $instrument_id,
        ':order_type'        => $order_type,
        ':quantity'          => $quantity,
        ':price'             => $price,
        ':fees'              => $fees,
        ':trade_date'        => $trade_date,
        ':settled'           => $settled,
        ':status'            => 'ACTIVE',
        ':cancelled_at'      => null
    ]);

    $order_id = (int)$pdo->lastInsertId();
    error_log("DEBUG: inserted order_transaction with id $order_id");

    // ----------------------
    // Vérifier cash account
    // ----------------------
    $chk = $pdo->prepare("SELECT has_cash_account FROM broker_account WHERE id = :id LIMIT 1");
    $chk->execute([':id' => $broker_account_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    error_log("DEBUG: broker_account row = " . json_encode($row));

    if ($row && (int)$row['has_cash_account'] === 1) {
        $amt = ($order_type === 'BUY') ? round($quantity*$price + $fees,2) : round($quantity*$price - $fees,2);
        $amount = ($order_type === 'BUY') ? -abs($amt) : abs($amt);
        $ctype = $order_type;

        $cash_date = $trade_date ;
        $ins = $pdo->prepare("
            INSERT INTO cash_transaction
            (broker_account_id, date, amount, type, reference_id, comment)
            VALUES (:broker_account_id, :date, :amount, :type, :reference_id, NULL)
        ");
        $ins->execute([
            ':broker_account_id' => $broker_account_id,
            ':date' => $cash_date,
            ':amount' => $amount,
            ':type' => $ctype,
            ':reference_id' => $order_id
        ]);
        error_log("DEBUG: inserted cash_transaction, amount=$amount");

        // Mettre à jour current_balance
        $sumStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount),0) AS sum_amount
            FROM cash_transaction
            WHERE broker_account_id = :broker_account_id
        ");
        $sumStmt->execute([':broker_account_id' => $broker_account_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
        error_log("DEBUG: sum of cash_transaction = " . json_encode($sumRow));

        if ($sumRow) {
            $upd = $pdo->prepare("
                UPDATE cash_account
                SET current_balance = :bal, updated_at = NOW()
                WHERE broker_account_id = :broker_account_id
            ");
            $upd->execute([
                ':bal' => $sumRow['sum_amount'],
                ':broker_account_id' => $broker_account_id
            ]);
            error_log("DEBUG: updated cash_account current_balance=" . $sumRow['sum_amount']);
        }
    }

    $pdo->commit();
    error_log("DEBUG: transaction committed");

    ob_end_clean(); // vider le buffer pour garantir un JSON pur
    echo json_encode(['success' => true, 'order_id' => $order_id]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean(); // vider le buffer
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

