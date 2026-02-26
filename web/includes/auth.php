<?php
/**
 * ------------------------------------------------------------
 * CashCue - Authentication & Session Guard (v3 - Hardened)
 * 
 * This file centralizes all authentication logic for both web and API contexts.
 * Key features:
 *   - Prevents direct access to the file
 *   - Starts session safely
 *   - Detects if the request is for API or web
 *   - Handles both session-based and token-based authentication
 *   - Implements RBAC with helper functions
 *   - Enforces session timeout
 *   - Provides broker access control for standard users
 * Usage:
 *   - Include this file at the top of any web page or API endpoint that requires authentication
 *   - Use isSuperAdmin() and requireSuperAdmin() for role checks
 *   - Use assertBrokerAccess($brokerId) to enforce broker-level access control
 * ------------------------------------------------------------
*/

# ------------------------------------------------------------
# 1️⃣ Prevent direct access
# ------------------------------------------------------------
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit('Direct access to this file is not allowed.');
}

# ------------------------------------------------------------
# 2️⃣ Start session safely
# ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

# ------------------------------------------------------------
# 3️⃣ Detect API context
# ------------------------------------------------------------
$isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;

# ------------------------------------------------------------
# 4️⃣ Authentication
# ------------------------------------------------------------
$authenticated = false;

$currentUserId    = null;
$currentUserEmail = null;
$currentUserRole  = null;

# ------------------------------------------------------------
# 4a️⃣ Standard web session authentication
# ------------------------------------------------------------
if (!empty($_SESSION['user_id'])) {

    require_once __DIR__ . '/../config/database.php';

    $db  = new Database('production');
    $pdo = $db->getConnection();

    // Revalidate user status from DB (important if user suspended)
    $stmt = $pdo->prepare("
        SELECT id, email, is_super_admin, is_active
        FROM user
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && (int)$user['is_active'] === 1) {

        $authenticated       = true;
        $currentUserId       = (int)$user['id'];
        $currentUserEmail    = $user['email'];
        $_SESSION['is_super_admin'] = (bool)$user['is_super_admin'];
        $currentUserRole     = $_SESSION['is_super_admin'] ? 'SUPER_ADMIN' : 'USER';

    } else {
        // User suspended or deleted → destroy session
        session_unset();
        session_destroy();
    }
}

# ------------------------------------------------------------
# 4b️⃣ API Token authentication
# ------------------------------------------------------------
if (!$authenticated) {

    $apiToken = null;

    // Robust Authorization header retrieval
    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? null;

    if (!$authHeader && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? null;
    }

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $apiToken = $matches[1];
    }

    // Fallback GET/POST
    if (!$apiToken) {
        $apiToken = $_REQUEST['api_token'] ?? null;
    }

    if ($apiToken) {

        require_once __DIR__ . '/../config/database.php';

        $db  = new Database('production');
        $pdo = $db->getConnection();

        $tokenHash = hash('sha256', $apiToken);

        $stmt = $pdo->prepare("
            SELECT 
                uat.id AS token_id,
                u.id AS user_id,
                u.email,
                u.is_super_admin
            FROM user_api_token uat
            INNER JOIN user u ON u.id = uat.user_id
            WHERE uat.token_hash = :token_hash
              AND uat.is_revoked = 0
              AND (uat.expires_at IS NULL OR uat.expires_at > NOW())
              AND u.is_active = 1
            LIMIT 1
        ");

        $stmt->execute(['token_hash' => $tokenHash]);
        $tokenUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tokenUser) {

            $authenticated    = true;
            $currentUserId    = (int)$tokenUser['user_id'];
            $currentUserEmail = $tokenUser['email'];
            $_SESSION['is_super_admin'] = (bool)$tokenUser['is_super_admin'];
            $currentUserRole  = $_SESSION['is_super_admin'] ? 'SUPER_ADMIN' : 'USER';

            // Update last_used_at safely
            $upd = $pdo->prepare("
                UPDATE user_api_token
                SET last_used_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([(int)$tokenUser['token_id']]);
        }
    }
}

# ------------------------------------------------------------
# 4c️⃣ Stop if not authenticated
# ------------------------------------------------------------
if (!$authenticated) {

    if ($isApiRequest) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error'   => 'Unauthorized'
        ]);
    } else {
        header("Location: /cashcue/views/login.php");
    }
    exit;
}

# ------------------------------------------------------------
# 5️⃣ Session timeout
# ------------------------------------------------------------
if (
    isset($_SESSION['LAST_ACTIVITY'], $_SESSION['SESSION_DURATION']) &&
    (time() - $_SESSION['LAST_ACTIVITY']) > $_SESSION['SESSION_DURATION']
) {
    session_unset();
    session_destroy();

    if ($isApiRequest) {
        http_response_code(401);
        echo json_encode(['success'=>false,'error'=>'Session expired']);
    } else {
        header("Location: /cashcue/views/login.php?expired=1");
    }
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();

# ------------------------------------------------------------
# 6️⃣ RBAC Helpers
# ------------------------------------------------------------
function isSuperAdmin(): bool
{
    return ($_SESSION['is_super_admin'] ?? false) === true;
}

function requireSuperAdmin(): void
{
    global $isApiRequest;

    if (!isSuperAdmin()) {
        if ($isApiRequest) {
            http_response_code(403);
            echo json_encode(['success'=>false,'error'=>'Forbidden']);
        } else {
            http_response_code(403);
            echo "Access denied.";
        }
        exit;
    }
}

# ------------------------------------------------------------
# 7️⃣ Broker access control
# ------------------------------------------------------------
function assertBrokerAccess(int $brokerId): void
{
    global $currentUserId, $isApiRequest;

    if (isSuperAdmin()) return;

    require_once __DIR__ . '/../config/database.php';
    $db  = new Database('production');
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_broker_account
        WHERE user_id = :user_id
          AND broker_account_id = :broker_id
        LIMIT 1
    ");
    $stmt->execute([
        'user_id'   => $currentUserId,
        'broker_id' => $brokerId
    ]);

    if (!$stmt->fetchColumn()) {

        $logFile = __DIR__ . '/../logs/unauthorized_access.log';
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri     = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $method  = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        $timestamp = date('Y-m-d H:i:s');

        $logLine = sprintf(
            "[%s] Unauthorized broker access: user_id=%d, broker_id=%d, ip=%s, method=%s, uri=%s\n",
            $timestamp,$currentUserId,$brokerId,$ip,$method,$uri
        );

        file_put_contents($logFile,$logLine,FILE_APPEND|LOCK_EX);

        if ($isApiRequest) {
            http_response_code(403);
            echo json_encode(['success'=>false,'error'=>'Access denied to this broker account']);
        } else {
            http_response_code(403);
            echo "Access denied.";
        }
        exit;
    }
}