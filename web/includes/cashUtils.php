<?php

function recalculateCashBalance(PDO $pdo, int $broker_id): void
{
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM cash_transaction
        WHERE broker_account_id = :broker_id
    ");
    $stmt->execute([':broker_id' => $broker_id]);
    $balance = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        UPDATE cash_account
        SET current_balance = :balance,
            updated_at = NOW()
        WHERE broker_account_id = :broker_id
    ");
    $stmt->execute([
        ':balance'   => $balance,
        ':broker_id'=> $broker_id
    ]);
}
