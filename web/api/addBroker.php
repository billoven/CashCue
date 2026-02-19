<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {

    $db = new Database('production');
    $pdo = $db->getConnection();

    // Set error mode to exceptions
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ==============================
    // POST fields
    // Adapt field names and types as needed
    // ==============================
    $name            = trim($_POST['name'] ?? '');
    $account_number  = trim($_POST['account_number'] ?? '');
    $account_type    = trim($_POST['account_type'] ?? 'PEA');
    $currency        = strtoupper(trim($_POST['currency'] ?? 'EUR'));
    $has_cash        = !empty($_POST['has_cash_account']) ? 1 : 0;
    $initial_deposit = floatval($_POST['initial_deposit'] ?? 0);
    $comment         = trim($_POST['comment'] ?? '');

    // ==============================
    // Basic validation (can be expanded with more complex rules)
    // For example, you could check if the account number is unique here before attempting to insert.
    // You could also validate the currency against a list of supported currencies, or check that the account type is valid.
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

    // ==============================
    // Insert broker_account
    // if has_cash_account is true, we will create a linked cash account in the next step
    // We also set the status to 'ACTIVE' by default, but this can be adjusted based on your needs
    // The account_number field is optional, but if provided, it should be unique. You can enforce this with a UNIQUE constraint in your database schema and handle the exception if a duplicate is attempted.
    // The created_at field is set to the current timestamp using NOW(), but you could also allow this to be provided in the POST data if you want to support backdating.
    // The comment field is required in this example, but you could make it optional if you prefer. It can be used to store any additional information about the broker account that might be useful for future reference.
    // ==============================
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

    // ==============================
    // Cash account creation
    // If the broker account has a cash account, we create it here and link it to the broker account using the broker_account_id foreign key.
    // The initial balance of the cash account is set to the initial deposit amount provided in the POST data. The current balance is also initialized to the same amount, but in a real application, you might want to calculate this based on existing transactions if you're allowing backdating.
    // The name of the cash account is derived from the broker account name for clarity, but you could allow this to be specified separately if you want more flexibility.
    // If the initial deposit is greater than zero, we also create an initial transaction to reflect this deposit in the cash account. The transaction type is set to 'DEPOSIT', but you could use different types or allow this to be specified in the POST data if you want more flexibility.
    // The comment for the initial transaction includes the provided comment from the POST data, prefixed with "Initial deposit — " to indicate that this transaction is related to the initial funding of the cash account.
    // ==============================
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

        // ==============================
        // Initial transaction (if > 0)
        // ==============================
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
    // Commit transaction
    // ==============================
    $pdo->commit();

    // ==============================
    // Success response
    // ==============================
    echo json_encode([
        'success' => true,
        'message' => 'Broker created successfully.',
        'broker_id' => $broker_id
    ]);

} catch (Throwable $e) {

    // Rollback transaction if something went wrong
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Handle specific errors (e.g., duplicate account number) or return a generic error message
    if (str_contains($e->getMessage(), 'Duplicate')) {

        echo json_encode([
            'success' => false,
            'message' => 'Account number already exists.'
        ]);

    } else {

        // For debugging purposes, you might want to log the error message to a file or monitoring system instead of returning it directly in the response, especially in a production environment. This is to avoid exposing sensitive information about your database structure or application logic to potential attackers.
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

