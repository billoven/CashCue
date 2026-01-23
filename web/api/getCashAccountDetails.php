<?php
require_once __DIR__ . '/../config/Database.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) throw new Exception('Missing id');

    $id = intval($_GET['id']);
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        SELECT ca.*, b.name AS broker_name
        FROM cash_account ca
        LEFT JOIN broker_account b ON ca.broker_account_id = b.id
        WHERE ca.id = :id
    ");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'Cash account not found']);
        exit;
    }

    echo json_encode($row);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
