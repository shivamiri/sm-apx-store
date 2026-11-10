<?php
/**
 * GET /api/app_config.php
 * Returns app version info, update URL and banner from system_settings.
 */
require_once __DIR__ . '/../common/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!checkRateLimit($ip, 'app_config', 60, 60)) {
    jsonResponse(false, null, 'Too many requests', 429);
}

try {
    $pdo = getDbConnection();
    $keys = [
        'min_app_version_code',
        'latest_app_version_code',
        'update_apk_url',
        'force_update_message',
        'announcement_banner',
    ];

    $rows = $pdo->query('SELECT setting_key, setting_value FROM system_settings')
                ->fetchAll(PDO::FETCH_KEY_PAIR);

    $data = [];
    foreach ($keys as $key) {
        $data[$key] = $rows[$key] ?? '';
    }
    $data['min_app_version_code']    = (int) $data['min_app_version_code'];
    $data['latest_app_version_code'] = (int) $data['latest_app_version_code'];

    jsonResponse(true, $data, null);
} catch (Throwable $e) {
    jsonResponse(false, null, 'Server error', 500);
}
