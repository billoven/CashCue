<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = new Database('development');
    $pdo = $db->getConnection();

    // If an id is provided, return single broker
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $pdo->prepare("SELECT * FROM broker_account WHERE id = ?");
        $stmt->execute([$id]);
        $broker = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($broker ?: []);
        exit;
    }

    // Otherwise return all brokers
    $stmt = $pdo->query("SELECT * FROM broker_account ORDER BY created_at DESC");
    $brokers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($brokers);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

