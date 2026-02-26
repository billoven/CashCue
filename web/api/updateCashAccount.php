<?php
/**
 * API endpoint to update a cash account (broker_account)
 * 
 * Expected POST parameters:
 * - id (required): ID of the broker account to update
 * - name (required): Name of the broker account
 * - account_number (optional): Account number (must be unique if provided)
 * - account_type (optional): Type of account (e.g., PEA, standard)
 * - currency (optional): Currency code (default: EUR)
 * - comment (required): Comment or description for the account
 * 
 * Response format:
 * {
 *   success: true/false,
 *   message: "Description of the result"
 * }
 */
header('Content-Type: application/json; charset=utf-8');

// define a constant to indicate that we are in the CashCue app context
// This can be used in included files to conditionally execute code (e.g., skipping certain checks or including specific assets)
define('CASHCUE_APP', true);

// Include authentication check
require_once __DIR__ . '/../includes/auth.php';

// include database connection class
require_once __DIR__ . '/../config/database.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["id"])) {
        throw new Exception("Missing cash account id");
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $fields = [];
    $params = [":id" => $data["id"]];

    if (isset($data["name"])) {
        $fields[] = "name = :name";
        $params[":name"] = $data["name"];
    }

    if (isset($data["current_balance"])) {
        $fields[] = "current_balance = :current_balance";
        $params[":current_balance"] = $data["current_balance"];
    }

    if (empty($fields)) {
        throw new Exception("No fields to update");
    }

    $sql = "UPDATE cash_account SET " . implode(", ", $fields) . " WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()]);
}
