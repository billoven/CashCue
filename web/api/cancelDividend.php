<?php

header('Content-Type: application/json; charset=utf-8');

// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../includes/auth.php';

// include database connection class
require_once __DIR__ . '/../config/database.php';

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!isset($data['id'])) {
        throw new Exception('Missing dividend id');
    }

    $div_id = (int)$data['id'];

    $db = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    // 1️⃣ Récupérer le dividend actif
    $sel = $pdo->prepare("
        SELECT id, broker_account_id, amount, status 
        FROM dividend 
        WHERE id = :id 
        LIMIT 1
    ");
    $sel->execute([':id' => $div_id]);
    $dividend = $sel->fetch(PDO::FETCH_ASSOC);
    if (!$dividend) throw new Exception('Dividend not found');
    if ($dividend['status'] !== 'ACTIVE') throw new Exception('Dividend is already cancelled');

    $broker_account_id = (int)$dividend['broker_account_id'];
    
    // Récupérer le montant du dernier cash transaction lié au dividend
        $cur = $pdo->prepare("
        SELECT amount
        FROM cash_transaction
        WHERE reference_id = :div_id
        AND type = 'DIVIDEND'
        ORDER BY id DESC
        LIMIT 1
    ");
    $cur->execute([':div_id' => $div_id]);
    $latest = $cur->fetch(PDO::FETCH_ASSOC);
    $amount = (float)$latest['amount'];

    // 2️⃣ Marquer le dividend comme CANCELLED
    $updDividend = $pdo->prepare("
        UPDATE dividend
        SET status = 'CANCELLED', cancelled_at = NOW()
        WHERE id = :id
    ");
    $updDividend->execute([':id' => $div_id]);

    // 3️⃣ Créer le cash reversal
    $insCash = $pdo->prepare("
        INSERT INTO cash_transaction
        (broker_account_id, date, amount, type, reference_id, comment)
        VALUES (:broker_account_id, NOW(), :amount, 'DIVIDEND', :ref, :comment)
    ");
    $insCash->execute([
        ':broker_account_id' => $broker_account_id,
        ':amount' => -$amount, // Reversal
        ':ref' => $div_id,
        ':comment' => "Reversal of dividend #$div_id"
    ]);

    // 4️⃣ Mettre à jour le solde cash du broker
    $sumStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount),0) AS sum_amount 
        FROM cash_transaction 
        WHERE broker_account_id = :broker_account_id
    ");
    $sumStmt->execute([':broker_account_id' => $broker_account_id]);
    $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);

    $updBalance = $pdo->prepare("
        UPDATE cash_account 
        SET current_balance = :bal, updated_at = NOW() 
        WHERE broker_account_id = :broker_account_id
    ");
    $updBalance->execute([
        ':bal' => $sumRow['sum_amount'],
        ':broker_account_id' => $broker_account_id
    ]);

    $pdo->commit();

    // ✅ Retour JSON complet
    echo json_encode([
        'success' => true,
        'dividend_id' => $div_id,
        'new_status' => 'CANCELLED',
        'cash_reversal_amount' => -$amount,
        'broker_balance' => $sumRow['sum_amount']
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

