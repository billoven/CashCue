<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = new Database('development');
    $pdo = $db->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

    $id = $data['id'] ?? null;
    if (!$id) {
        throw new Exception("Missing dividend ID");
    }

    $broker_id = $data['broker_id'] ?? null;
    $instrument_id = $data['instrument_id'] ?? null;
    $payment_date = $data['payment_date'] ?? null;
    $gross_amount = $data['gross_amount'] ?? null;
    $taxes_withheld = $data['taxes_withheld'] ?? 0.0000;
    $amount = $data['amount'] ?? null;
    $currency = $data['currency'] ?? 'EUR';

    if ($amount === null && $gross_amount !== null) {
        $amount = $gross_amount - $taxes_withheld;
    }

    $sql = "UPDATE dividend 
            SET broker_id = :broker_id,
                instrument_id = :instrument_id,
                payment_date = :payment_date,
                gross_amount = :gross_amount,
                taxes_withheld = :taxes_withheld,
                amount = :amount,
                currency = :currency
            WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':broker_id' => $broker_id,
        ':instrument_id' => $instrument_id,
        ':payment_date' => $payment_date,
        ':gross_amount' => $gross_amount,
        ':taxes_withheld' => $taxes_withheld,
        ':amount' => $amount,
        ':currency' => $currency,
        ':id' => $id
    ]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
