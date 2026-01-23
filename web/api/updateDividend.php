<?php
/**
 * updateDividend.php
 *
 * Updates a dividend record and synchronizes the related cash transaction.
 *
 * Business rules:
 * - The dividend table is the single source of truth.
 * - Cash transactions are projections of dividends.
 * - Explicit net amount ("amount") has priority over computed net.
 * - If net is not explicit, it is recalculated as gross_amount - taxes_withheld.
 */

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

try {

    // --------------------------------------------------
    // 1) Read and validate input
    // --------------------------------------------------
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input || !isset($input['id'])) {
        throw new Exception('Missing dividend id in input');
    }

    $div_id = (int)$input['id'];

    // --------------------------------------------------
    // 2) Database connection & transaction
    // --------------------------------------------------
    $db  = new Database();
    $pdo = $db->getConnection();
    $pdo->beginTransaction();

    // --------------------------------------------------
    // 3) Load existing dividend (pre-update)
    // --------------------------------------------------
    $sel = $pdo->prepare("SELECT * FROM dividend WHERE id = :id LIMIT 1");
    $sel->execute([':id' => $div_id]);
    $existing = $sel->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        throw new Exception('Dividend not found');
    }

    $broker_account_id = (int)$existing['broker_account_id'];

    // --------------------------------------------------
    // 4) Build UPDATE statement dynamically
    // --------------------------------------------------
    $allowed = [
        'instrument_id',
        'amount',          // explicit net amount
        'gross_amount',
        'currency',
        'payment_date',
        'taxes_withheld'
    ];

    $fields = [];
    $params = [':id' => $div_id];

    foreach ($allowed as $field) {
        if (array_key_exists($field, $input)) {
            $fields[] = "$field = :$field";
            $params[":$field"] = $input[$field];
        }
    }

    if (empty($fields)) {
        throw new Exception('No fields to update');
    }

    $sql = "UPDATE dividend SET " . implode(', ', $fields) . " WHERE id = :id";
    $upd = $pdo->prepare($sql);
    $upd->execute($params);

    // --------------------------------------------------
    // 5) Reload dividend AFTER update (single source of truth)
    // --------------------------------------------------
    $sel = $pdo->prepare("SELECT * FROM dividend WHERE id = :id LIMIT 1");
    $sel->execute([':id' => $div_id]);
    $div = $sel->fetch(PDO::FETCH_ASSOC);

    if (!$div) {
        throw new Exception('Dividend disappeared after update');
    }

    // --------------------------------------------------
    // 6) Remove existing cash transactions for this dividend
    // --------------------------------------------------
    $del = $pdo->prepare("
        DELETE FROM cash_transaction
        WHERE reference_id = :ref
          AND type = 'DIVIDEND'
    ");
    $del->execute([':ref' => $div_id]);

    // --------------------------------------------------
    // 7) Check if broker has a cash account
    // --------------------------------------------------
    $chk = $pdo->prepare("
        SELECT has_cash_account
        FROM broker_account
        WHERE id = :broker_account_id
        LIMIT 1
    ");
    $chk->execute([':broker_account_id' => $broker_account_id]);
    $broker = $chk->fetch(PDO::FETCH_ASSOC);

    if ($broker && (int)$broker['has_cash_account'] === 1) {

        // --------------------------------------------------
        // 8) Compute net cash amount
        // --------------------------------------------------
        if (array_key_exists('amount', $input)) {
            // Explicit net amount has absolute priority
            $net_amount = (float)$input['amount'];
        } else {
            // Recalculate from updated dividend values
            $gross = (float)$div['gross_amount'];
            $taxes = (float)$div['taxes_withheld'];
            $net_amount = $gross - $taxes;
        }

        // --------------------------------------------------
        // 9) Insert new cash transaction
        // --------------------------------------------------
        $ins = $pdo->prepare("
            INSERT INTO cash_transaction
                (broker_account_id, date, amount, type, reference_id, comment)
            VALUES
                (:broker_account_id, :date, :amount, 'DIVIDEND', :reference_id, NULL)
        ");

        $ins->execute([
            ':broker_account_id'    => $broker_account_id,
            ':date'         => $div['payment_date'] . ' 00:00:00',
            ':amount'       => $net_amount,
            ':reference_id' => $div_id
        ]);

        // --------------------------------------------------
        // 10) Recompute cash account balance
        // --------------------------------------------------
        $sumStmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) AS sum_amount
            FROM cash_transaction
            WHERE broker_account_id = :broker_account_id
        ");
        $sumStmt->execute([':broker_account_id' => $broker_account_id]);
        $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);

        if ($sumRow) {
            $updBal = $pdo->prepare("
                UPDATE cash_account
                SET current_balance = :bal,
                    updated_at = NOW()
                WHERE broker_account_id = :broker_account_id
            ");
            $updBal->execute([
                ':bal'       => $sumRow['sum_amount'],
                ':broker_account_id' => $broker_account_id
            ]);
        }
    }

    // --------------------------------------------------
    // 11) Commit transaction
    // --------------------------------------------------
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}

