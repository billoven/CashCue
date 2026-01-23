<?php
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: application/json');

try {
    $brokerId = $_GET["broker_account_id"] ?? null;

    $db = new Database();
    $pdo = $db->getConnection();

    if ($brokerId) {
        $stmt = $pdo->prepare("SELECT * FROM cash_account WHERE broker_account_id = :broker_account_id");
        $stmt->execute([":broker_account_id" => $brokerId]);
    } else {
        $stmt = $pdo->query("SELECT * FROM cash_account");
    }

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()]);
}
