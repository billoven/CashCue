<?php
/**
 * ------------------------------------------------------------
 * CashCue - API Token Generator CLI
 * ------------------------------------------------------------
 * Usage: 
 *   php generate_api_token.php <user_id> <token_name>
 *
 * This script generates a cryptographically secure token for a user,
 * stores only its SHA256 hash in the database, and outputs the token
 * once so the user can copy it.
 * ba237b37fb7db63e55f59e2fa10eac752007c63fe55301b529acf06ae62bb8b3
 * ------------------------------------------------------------
 */

require_once __DIR__ . '/../web/config/database.php';

if ($argc < 3) {
    echo "Usage: php generate_api_token.php <user_id> <token_name>\n";
    exit(1);
}

$userId    = (int)$argv[1];
$tokenName = $argv[2];

try {
    $db  = new Database('production');
    $pdo = $db->getConnection();

    // Generate secure random token
    $plainToken = bin2hex(random_bytes(32)); // 64 characters
    $tokenHash  = hash('sha256', $plainToken);

    $expireAt = (new DateTime())->modify('+3 months')->format('Y-m-d H:i:s');

    // Insert token into database with default expiration
    $stmt = $pdo->prepare("
        INSERT INTO user_api_token (user_id, name, token_hash, created_at, expires_at)
        VALUES (:user_id, :name, :token_hash, NOW(), :expires_at)
    ");
    $stmt->execute([
        'user_id'    => $userId,
        'name'       => $tokenName,
        'token_hash' => $tokenHash,
        'expires_at' => $expireAt
    ]);


    echo "✅ API Token generated successfully!\n";
    echo "User ID: $userId\n";
    echo "Token Name: $tokenName\n";
    echo "Token will expire at: $expireAt\n";
    echo "Token (copy this now, it won't be stored in plain text):\n";
    echo "$plainToken\n";
    

} catch (Throwable $e) {
    echo "❌ Error generating API token: " . $e->getMessage() . "\n";
    exit(1);
}