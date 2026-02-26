<?php
/**
 * addBroker.php
 *
 * Creates a broker_account and optionally:
 *  - creates a linked cash_account
 *  - creates an initial DEPOSIT transaction
 *
 * Additionally:
 *  - automatically links the created broker_account
 *    to the currently authenticated user
 *    via user_broker_account (ownership layer).
 *
 * All operations are executed inside a single
 * atomic database transaction.
 */

header('Content-Type: application/json');

define('CASHCUE_APP', true);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

try {

    // =====================================================
    // Retrieve authenticated user (required for ownership)
    // =====================================================
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated.');
    }

    $user_id = intval($_SESSION['user_id']);

    $db = new Database('production');
    $pdo = $db->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==============================
    // POST fields
    // ==============================
    $name            = trim($_POST['name'] ?? '');
    $account_number  = trim($_POST['account_number'] ?? '');
    $account_type    = trim($_POST['account_type'] ?? 'PEA');
    $currency        = strtoupper(trim($_POST['currency'] ?? 'EUR'));
    $has_cash        = !empty($_POST['has_cash_account']) ? 1 : 0;
    $initial_deposit = floatval($_POST['initial_deposit'] ?? 0);
    $comment         = trim($_POST['comment'] ?? '');

    // ==============================
    // Validation
    // ==============================
    if ($name === '') {
        throw new Exception('Broker name is required.');
    }

    if ($comment === '') {
        throw new Exception('Comment is required.');
    }

    if ($initial_deposit < 0) {
        throw new Exception('Initial deposit cannot be negative.');
    }

    // ==============================
    // TRANSACTION START
    // ==============================
    $pdo->beginTransaction();

    // =====================================================
    // 1️⃣ Insert broker_account
    // =====================================================
    $stmt = $pdo->prepare("
        INSERT INTO broker_account (
            name,
            account_number,
            account_type,
            currency,
            created_at,
            has_cash_account,
            status,
            comment
        )
        VALUES (
            :name,
            :account_number,
            :account_type,
            :currency,
            NOW(),
            :has_cash_account,
            'ACTIVE',
            :comment
        )
    ");

    $stmt->execute([
        ':name' => $name,
        ':account_number' => $account_number ?: null,
        ':account_type' => $account_type,
        ':currency' => $currency,
        ':has_cash_account' => $has_cash,
        ':comment' => $comment
    ]);

    $broker_id = $pdo->lastInsertId();

    // =====================================================
    // 2️⃣ Link broker_account to authenticated user
    // (Ownership mapping layer)
    // =====================================================
    $stmtLink = $pdo->prepare("
        INSERT INTO user_broker_account (
            user_id,
            broker_account_id
        )
        VALUES (
            :user_id,
            :broker_id
        )
    ");

    $stmtLink->execute([
        ':user_id' => $user_id,
        ':broker_id' => $broker_id
    ]);

    // =====================================================
    // 3️⃣ Optional cash_account creation
    // =====================================================
    if ($has_cash) {

        $stmtCash = $pdo->prepare("
            INSERT INTO cash_account (
                broker_account_id,
                name,
                initial_balance,
                current_balance,
                created_at
            )
            VALUES (
                :broker_id,
                :name,
                :initial,
                :current,
                NOW()
            )
        ");

        $stmtCash->execute([
            ':broker_id' => $broker_id,
            ':name' => $name . ' Cash Account',
            ':initial' => $initial_deposit,
            ':current' => $initial_deposit
        ]);

        // =====================================================
        // 4️⃣ Optional initial deposit transaction
        // =====================================================
        if ($initial_deposit > 0) {

            $stmtTx = $pdo->prepare("
                INSERT INTO cash_transaction (
                    broker_account_id,
                    date,
                    amount,
                    type,
                    comment
                )
                VALUES (
                    :broker_id,
                    NOW(),
                    :amount,
                    'DEPOSIT',
                    :comment
                )
            ");

            $stmtTx->execute([
                ':broker_id' => $broker_id,
                ':amount' => $initial_deposit,
                ':comment' => 'Initial deposit — ' . $comment
            ]);
        }
    }

    // ==============================
    // Commit
    // ==============================
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Broker created successfully.',
        'broker_id' => $broker_id
    ]);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (str_contains($e->getMessage(), 'Duplicate')) {

        echo json_encode([
            'success' => false,
            'message' => 'Account number already exists.'
        ]);

    } else {

        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
