<?php
/**
 * GET /api/search.php?q=<text>&page=<n>&limit=<n>
 * Tier 1: app name / package name matches.
 * If none: Tier 2 (similar by words or category) + Tier 3 (trending apps),
 * with fallback_applied = true.
 */
require_once __DIR__ . '/../common/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!checkRateLimit($ip, 'search', 60, 60)) {
    jsonResponse(false, null, 'Too many requests', 429);
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if ($q === '') {
    jsonResponse(false, null, "Search query parameter 'q' is required.", 400);
}
if (mb_strlen($q) > 100) {
    $q = mb_substr($q, 0, 100);
}

$page  = isset($_GET['page'])  && ctype_digit((string) $_GET['page'])  ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) && ctype_digit((string) $_GET['limit']) ? (int) $_GET['limit'] : 20;
$limit = max(1, min(50, $limit));

function likeEscape(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

function cleanRows(array $rows): array
{
    foreach ($rows as &$r) {
        $r['id']             = (int) $r['id'];
        $r['rating_average'] = (float) $r['rating_average'];
        $r['download_count'] = (int) $r['download_count'];
    }
    unset($r);
    return $rows;
}

$cols = 'a.id, a.app_name, a.package_name, c.name AS category, a.version_name,
         a.size_formatted, a.icon_url, a.rating_average, a.download_count';
$base = "FROM apps a JOIN categories c ON c.id = a.category_id
         WHERE a.status = 'Live' AND a.deleted_at IS NULL";

try {
    $pdo  = getDbConnection();
    $like = '%' . likeEscape($q) . '%';

    // ---- Tier 1: direct matches ----
    $where1 = ' AND (a.app_name LIKE :q1 OR a.package_name LIKE :q2)';
    $cnt = $pdo->prepare('SELECT COUNT(*) ' . $base . $where1);
    $cnt->execute([':q1' => $like, ':q2' => $like]);
    $total = (int) $cnt->fetchColumn();

    if ($total > 0) {
        $offset = ($page - 1) * $limit;
        $stmt = $pdo->prepare(
            'SELECT ' . $cols . ' ' . $base . $where1 .
            ' ORDER BY (a.app_name LIKE :p) DESC, a.download_count DESC, a.id DESC
              LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $stmt->execute([':q1' => $like, ':q2' => $like, ':p' => likeEscape($q) . '%']);
        $pages = (int) ceil($total / $limit);

        jsonResponse(true, [
            'query'             => $q,
            'fallback_applied'  => false,
            'fallback_message'  => null,
            'results'           => cleanRows($stmt->fetchAll()),
            'pagination'        => [
                'current_page'  => $page,
                'per_page'      => $limit,
                'total_records' => $total,
                'total_pages'   => $pages,
                'has_next_page' => $page < $pages,
                'has_prev_page' => $page > 1,
            ],
        ], null);
    }

    // ---- Tier 2: similar (words in description or category name) ----
    $results = [];
    $seen    = [];
    $words   = array_slice(array_values(array_unique(array_filter(
        preg_split('/\s+/u', mb_strtolower($q)),
        function ($w) { return mb_strlen($w) >= 3; }
    ))), 0, 5);

    if ($words) {
        $conds  = [];
        $params = [];
        foreach ($words as $i => $w) {
            $conds[] = "(a.description LIKE :d$i OR c.name LIKE :c$i)";
            $params[":d$i"] = '%' . likeEscape($w) . '%';
            $params[":c$i"] = '%' . likeEscape($w) . '%';
        }
        $stmt = $pdo->prepare(
            'SELECT ' . $cols . ' ' . $base . ' AND (' . implode(' OR ', $conds) . ')
             ORDER BY a.rating_average DESC, a.download_count DESC LIMIT 5'
        );
        $stmt->execute($params);
        foreach (cleanRows($stmt->fetchAll()) as $r) {
            $r['recommendation_reason'] = 'Similar to your search';
            $results[] = $r;
            $seen[$r['id']] = true;
        }
    }

    // ---- Tier 3: trending apps ----
    $stmt = $pdo->query(
        'SELECT ' . $cols . ' ' . $base . '
         ORDER BY (a.download_count + a.review_count * 5) DESC, a.id DESC LIMIT 5'
    );
    foreach (cleanRows($stmt->fetchAll()) as $r) {
        if (isset($seen[$r['id']])) { continue; }
        $r['recommendation_reason'] = 'Trending on Apx';
        $results[] = $r;
        $seen[$r['id']] = true;
    }

    jsonResponse(true, [
        'query'             => $q,
        'fallback_applied'  => true,
        'fallback_message'  => "No exact matches found for '" . $q . "'. You might like these recommendations:",
        'results'           => $results,
        'pagination'        => [
            'current_page'  => 1,
            'per_page'      => count($results),
            'total_records' => count($results),
            'total_pages'   => 1,
            'has_next_page' => false,
            'has_prev_page' => false,
        ],
    ], null);
} catch (Throwable $e) {
    jsonResponse(false, null, 'Server error', 500);
}
