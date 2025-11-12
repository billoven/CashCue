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

    $stmt = $pdo->prepare("DELETE FROM dividend WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
