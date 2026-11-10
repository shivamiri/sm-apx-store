<?php
/**
 * GET /api/get_home.php?category=<slug|all>
 * Returns categories and curated app shelves (Live apps only).
 */
require_once __DIR__ . '/../common/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!checkRateLimit($ip, 'get_home', 120, 60)) {
    jsonResponse(false, null, 'Too many requests', 429);
}

$categorySlug = isset($_GET['category']) ? trim((string) $_GET['category']) : 'all';
if ($categorySlug === '') {
    $categorySlug = 'all';
}

try {
    $pdo = getDbConnection();

    $categories = $pdo->query(
        'SELECT id, name, slug FROM categories ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
    foreach ($categories as &$c) {
        $c['id'] = (int) $c['id'];
    }
    unset($c);

    $where  = "a.status = 'Live' AND a.deleted_at IS NULL";
    $params = [];
    if ($categorySlug !== 'all') {
        $where .= ' AND c.slug = :slug';
        $params[':slug'] = $categorySlug;
    }

    $fetchShelf = function (string $extraWhere, string $orderBy) use ($pdo, $where, $params): array {
        $sql = 'SELECT a.id, a.app_name, a.package_name, c.name AS category,
                       a.version_name, a.version_code, a.size_formatted, a.size_bytes,
                       a.icon_url, a.feature_graphic_url, a.rating_average,
                       a.download_count, a.is_admin_app
                FROM apps a
                JOIN categories c ON c.id = a.category_id
                WHERE ' . $where . $extraWhere . '
                ORDER BY ' . $orderBy . '
                LIMIT 10';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['id']             = (int) $r['id'];
            $r['version_code']   = (int) $r['version_code'];
            $r['size_bytes']     = (int) $r['size_bytes'];
            $r['rating_average'] = (float) $r['rating_average'];
            $r['download_count'] = (int) $r['download_count'];
            $r['is_admin_app']   = (bool) $r['is_admin_app'];
        }
        unset($r);
        return $rows;
    };

    $shelves = [
        'featured'     => $fetchShelf(' AND a.is_featured = 1', 'a.updated_at DESC'),
        'hot_today'    => $fetchShelf('', 'a.download_count DESC, a.id DESC'),
        'new_arrivals' => $fetchShelf('', 'a.created_at DESC, a.id DESC'),
        'top_rated'    => $fetchShelf('', 'a.rating_average DESC, a.review_count DESC, a.id DESC'),
        'trending'     => $fetchShelf('', '(a.download_count + a.review_count * 5) DESC, a.id DESC'),
    ];

    jsonResponse(true, ['categories' => $categories, 'shelves' => $shelves], null);
} catch (Throwable $e) {
    jsonResponse(false, null, 'Server error', 500);
}
