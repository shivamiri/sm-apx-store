<?php
/**
 * SM Apx Store - core configuration (local development).
 * Change the DB values and SITE_URL when moving to the live host.
 */

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'sm_apx_store');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Site
define('APP_NAME', 'SM Apx Store');
define('APP_SHORT_NAME', 'Apx');
define('SITE_URL', 'http://localhost:8080');

// Google Sign-In (Phase 4.1): copy this file to config.php and paste the
// values from your OAuth client JSON. Never commit the real config.php.
define('GOOGLE_CLIENT_ID', 'PASTE_CLIENT_ID_HERE');
define('GOOGLE_CLIENT_SECRET', 'PASTE_CLIENT_SECRET_HERE');
define('GOOGLE_REDIRECT_URI', 'http://localhost:8080/login.php');

date_default_timezone_set('UTC');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns a shared PDO connection.
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'data'    => null,
            'error'   => 'Database connection failed',
        ]);
        exit;
    }
    return $pdo;
}

/**
 * Standard JSON envelope: {success, data, error}.
 */
function jsonResponse(bool $success, $data = null, $error = null, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'error'   => $error,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Database-driven rate limit. Returns true if the request is allowed,
 * false if the limit is exceeded in the current window.
 */
function checkRateLimit(string $ip, string $endpoint, int $limit, int $windowSeconds): bool
{
    $pdo = getDbConnection();
    $now = gmdate('Y-m-d H:i:s');
    $cutoff = gmdate('Y-m-d H:i:s', time() - $windowSeconds);

    // Start a new window if the old one expired, otherwise count the hit.
    $sql = 'INSERT INTO rate_limits (ip_address, endpoint, hits, window_start)
            VALUES (:ip, :ep, 1, :now)
            ON DUPLICATE KEY UPDATE
              hits = IF(window_start < :cutoff1, 1, hits + 1),
              window_start = IF(window_start < :cutoff2, :now2, window_start)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ip'     => $ip,
        ':ep'     => $endpoint,
        ':now'    => $now,
        ':cutoff1' => $cutoff,
        ':cutoff2' => $cutoff,
        ':now2'   => $now,
    ]);

    $stmt = $pdo->prepare('SELECT hits FROM rate_limits WHERE ip_address = :ip AND endpoint = :ep');
    $stmt->execute([':ip' => $ip, ':ep' => $endpoint]);
    $hits = (int) $stmt->fetchColumn();

    return $hits <= $limit;
}