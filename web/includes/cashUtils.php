<?php

<?php

/**
 * Recalcule le solde courant d’un cash_account
 * à partir des transactions associées.
*/
function recalculateCashBalance(PDO $pdo, int $broker_account_id): void
{
    // Somme de toutes les transactions liées à ce broker
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0)
        FROM cash_transaction
        WHERE broker_account_id = :broker_account_id
    ");
    $stmt->execute([':broker_account_id' => $broker_account_id]);
    $balance = (float) $stmt->fetchColumn();

    // Mise à jour du solde dans cash_account
    $stmt = $pdo->prepare("
        UPDATE cash_account
        SET current_balance = :balance,
            updated_at = NOW()
        WHERE broker_account_id = :broker_account_id
    ");
    $stmt->execute([
        ':balance'   => $balance,
        ':broker_account_id' => $broker_account_id
    ]);
}
