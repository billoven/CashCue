<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = new Database('development');
    $pdo = $db->getConnection();

    $data = json_decode(file_get_contents('php://input'), true);

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

    if (!$broker_id || !$instrument_id || !$payment_date || $amount === null) {
        throw new Exception("Missing required fields");
    }

    $sql = "INSERT INTO dividend (broker_id, instrument_id, payment_date, gross_amount, taxes_withheld, amount, currency)
            VALUES (:broker_id, :instrument_id, :payment_date, :gross_amount, :taxes_withheld, :amount, :currency)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':broker_id' => $broker_id,
        ':instrument_id' => $instrument_id,
        ':payment_date' => $payment_date,
        ':gross_amount' => $gross_amount,
        ':taxes_withheld' => $taxes_withheld,
        ':amount' => $amount,
        ':currency' => $currency
    ]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
