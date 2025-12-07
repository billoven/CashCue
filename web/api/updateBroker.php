<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('production');
    $pdo = $db->getConnection();

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $account_type = trim($_POST['account_type'] ?? 'PEA');
    $currency = strtoupper(trim($_POST['currency'] ?? 'EUR'));

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid broker ID.']);
        exit;
    }
    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Broker name cannot be empty.']);
        exit;
    }

    $stmt = $pdo->prepare("
        UPDATE broker_account
        SET name = ?, account_number = ?, account_type = ?, currency = ?
        WHERE id = ?
    ");
    $stmt->execute([$name, $account_number ?: null, $account_type, $currency, $id]);

    echo json_encode(['success' => true, 'message' => 'Broker updated successfully.']);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'message' => 'Account number already exists.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
