<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('development');
    $pdo = $db->getConnection();

    // Accept both application/x-www-form-urlencoded and multipart/form-data
    $name = trim($_POST['name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $account_type = trim($_POST['account_type'] ?? 'PEA');
    $currency = strtoupper(trim($_POST['currency'] ?? 'EUR'));

    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Broker name is required.']);
        exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO broker_account (name, account_number, account_type, currency)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$name, $account_number ?: null, $account_type, $currency]);

    echo json_encode(['success' => true, 'message' => 'Broker added successfully.']);
} catch (PDOException $e) {
    // handle duplicate account_number unique constraint
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'message' => 'Account number already exists.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

