<?php
/**
 * GET /api/get_app.php?id=<int>  OR  ?package_name=<string>
 * Returns full details of one Live app: category, developer, size,
 * download link, media, screenshots, metrics and latest reviews.
 */
require_once __DIR__ . '/../common/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!checkRateLimit($ip, 'get_app', 120, 60)) {
    jsonResponse(false, null, 'Too many requests', 429);
}

$id  = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$pkg = isset($_GET['package_name']) ? trim((string) $_GET['package_name']) : '';

if ($id === '' && $pkg === '') {
    jsonResponse(false, null, "Missing parameter. Provide either 'id' or 'package_name'.", 400);
}
if ($id !== '' && !ctype_digit($id)) {
    jsonResponse(false, null, "Invalid 'id'. It must be a number.", 400);
}
if ($id === '' && strlen($pkg) > 191) {
    jsonResponse(false, null, "Invalid 'package_name'.", 400);
}

try {
    $pdo = getDbConnection();

    $sql = "SELECT a.id, a.app_name, a.package_name, a.version_name, a.version_code,
                   a.apk_source_type, a.apk_source_url, a.size_bytes, a.size_formatted,
                   a.icon_url, a.feature_graphic_url, a.youtube_url, a.description,
                   a.is_admin_app, a.download_count, a.rating_average, a.review_count,
                   c.id AS category_id, c.name AS category_name, c.slug AS category_slug,
                   u.display_name AS developer_name
            FROM apps a
            JOIN categories c ON c.id = a.category_id
            LEFT JOIN users u ON u.id = a.user_id
            WHERE a.status = 'Live' AND a.deleted_at IS NULL AND ";
    if ($id !== '') {
        $stmt = $pdo->prepare($sql . 'a.id = :v LIMIT 1');
        $stmt->execute([':v' => (int) $id]);
    } else {
        $stmt = $pdo->prepare($sql . 'a.package_name = :v LIMIT 1');
        $stmt->execute([':v' => $pkg]);
    }
    $a = $stmt->fetch();

    if (!$a) {
        jsonResponse(false, null, 'App not found or not available', 404);
    }
    $appId = (int) $a['id'];

    $s = $pdo->prepare(
        'SELECT id, screenshot_url, sort_order FROM app_screenshots
         WHERE app_id = :id ORDER BY sort_order ASC, id ASC LIMIT 8'
    );
    $s->execute([':id' => $appId]);
    $screenshots = [];
    foreach ($s->fetchAll() as $row) {
        $screenshots[] = [
            'id'         => (int) $row['id'],
            'url'        => $row['screenshot_url'],
            'sort_order' => (int) $row['sort_order'],
        ];
    }

    $r = $pdo->prepare(
        'SELECT r.id, r.rating, r.comment, r.created_at,
                u.display_name AS user_name, u.avatar_url AS user_avatar
         FROM reviews r
         JOIN users u ON u.id = r.user_id
         WHERE r.app_id = :id AND r.deleted_at IS NULL AND u.deleted_at IS NULL
         ORDER BY r.created_at DESC, r.id DESC LIMIT 20'
    );
    $r->execute([':id' => $appId]);
    $reviews = [];
    foreach ($r->fetchAll() as $row) {
        $reviews[] = [
            'id'          => (int) $row['id'],
            'user_name'   => $row['user_name'],
            'user_avatar' => $row['user_avatar'],
            'rating'      => (int) $row['rating'],
            'comment'     => $row['comment'],
            'created_at'  => $row['created_at'],
        ];
    }

    $data = [
        'id'           => $appId,
        'app_name'     => $a['app_name'],
        'package_name' => $a['package_name'],
        'developer'    => [
            'name'     => $a['developer_name'] ?: 'Apx',
            'is_admin' => (bool) $a['is_admin_app'],
        ],
        'category'     => [
            'id'   => (int) $a['category_id'],
            'name' => $a['category_name'],
            'slug' => $a['category_slug'],
        ],
        'version'      => [
            'version_name' => $a['version_name'],
            'version_code' => (int) $a['version_code'],
        ],
        'size'         => [
            'formatted' => $a['size_formatted'],
            'bytes'     => (int) $a['size_bytes'],
        ],
        'download'     => [
            'source_type' => $a['apk_source_type'],
            'apk_url'     => $a['apk_source_url'],
        ],
        'media'        => [
            'icon_url'            => $a['icon_url'],
            'feature_graphic_url' => $a['feature_graphic_url'],
            'youtube_url'         => $a['youtube_url'],
            'screenshots'         => $screenshots,
        ],
        'description'  => $a['description'],
        'metrics'      => [
            'download_count' => (int) $a['download_count'],
            'rating_average' => (float) $a['rating_average'],
            'review_count'   => (int) $a['review_count'],
        ],
        'reviews'      => $reviews,
    ];

    jsonResponse(true, $data, null);
} catch (Throwable $e) {
    jsonResponse(false, null, 'Server error', 500);
}
