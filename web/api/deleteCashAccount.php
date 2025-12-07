<?php
require_once __DIR__ . '/../config/Database.php';
header('Content-Type: application/json');

try {
    if (!isset($_GET['id'])) throw new Exception('Missing id');

    $id = intval($_GET['id']);
    $db = new Database();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("DELETE FROM cash_account WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
