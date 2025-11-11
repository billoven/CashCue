<?php
require_once '../config/database.php';

header('Content-Type: application/json');

$db = new Database('development');
$pdo = $db->getConnection();

$sql = "
    SELECT 
        id,
        CONCAT(name, ' ', account_type) AS label,
        account_number,
        currency
    FROM broker_account
    ORDER BY name
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
