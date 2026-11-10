<?php
require_once __DIR__ . '/common/config.php';

$slug = isset($_GET['category']) ? trim((string) $_GET['category']) : 'all';
if ($slug === '') { $slug = 'all'; }

$pdo = getDbConnection();
$categories = $pdo->query('SELECT id, name, slug FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();

$where  = "a.status = 'Live' AND a.deleted_at IS NULL";
$params = [];
if ($slug !== 'all') {
    $where .= ' AND c.slug = :slug';
    $params[':slug'] = $slug;
}

function fetchApps(PDO $pdo, string $where, array $params, string $extra, string $order, int $limit): array
{
    $sql = 'SELECT a.id, a.app_name, a.package_name, c.name AS category, a.size_formatted,
                   a.icon_url, a.feature_graphic_url, a.rating_average, a.is_admin_app
            FROM apps a JOIN categories c ON c.id = a.category_id
            WHERE ' . $where . $extra . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$featured = fetchApps($pdo, $where, $params, ' AND a.is_featured = 1', 'a.updated_at DESC', 5);
$shelves = [
    'Hot Today'    => fetchApps($pdo, $where, $params, '', 'a.download_count DESC, a.id DESC', 10),
    'New Arrivals' => fetchApps($pdo, $where, $params, '', 'a.created_at DESC, a.id DESC', 10),
    'Trending'     => fetchApps($pdo, $where, $params, '', '(a.download_count + a.review_count * 5) DESC, a.id DESC', 10),
];
$allApps = fetchApps($pdo, $where, $params, '', 'a.app_name ASC', 60);

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function appCard(array $app): string
{
    $id = (int) $app['id'];
    return '<a class="app-card" href="app.php?id=' . $id . '">'
        . '<img class="app-icon" src="' . e($app['icon_url']) . '" alt="" loading="lazy">'
        . '<div class="app-card-title">' . e($app['app_name']) . '</div>'
        . '<div class="caption">' . e($app['category']) . '</div>'
        . '<div class="app-card-foot"><span class="size-pill">' . e($app['size_formatted']) . '</span>'
        . '<span class="get-btn">GET</span></div></a>';
}

$page_title = APP_NAME;
require_once __DIR__ . '/common/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/home.css">

<div class="chip-row">
  <a class="chip<?php echo $slug === 'all' ? ' active' : ''; ?>" href="index.php">All</a>
  <?php foreach ($categories as $c): ?>
    <a class="chip<?php echo $slug === $c['slug'] ? ' active' : ''; ?>" href="index.php?category=<?php echo urlencode($c['slug']); ?>"><?php echo e($c['name']); ?></a>
  <?php endforeach; ?>
</div>

<?php if ($featured): ?>
<section class="hero-row">
  <?php foreach ($featured as $f): ?>
    <a class="hero" href="app.php?id=<?php echo (int) $f['id']; ?>" style="background-image:url('<?php echo e($f['feature_graphic_url']); ?>')">
      <div class="hero-overlay">
        <span class="pill pill-live">Featured</span>
        <div class="heading-2"><?php echo e($f['app_name']); ?></div>
        <div class="caption"><?php echo e($f['category']); ?></div>
      </div>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php foreach ($shelves as $title => $apps): if (!$apps) { continue; } ?>
<section class="shelf">
  <h2 class="heading-2"><?php echo e($title); ?></h2>
  <div class="shelf-row">
    <?php foreach ($apps as $app) { echo appCard($app); } ?>
  </div>
</section>
<?php endforeach; ?>

<section class="shelf">
  <h2 class="heading-2">All Apps</h2>
  <?php if ($allApps): ?>
  <div class="app-grid">
    <?php foreach ($allApps as $app) { echo appCard($app); } ?>
  </div>
  <?php else: ?>
  <p class="text-secondary">No apps found.</p>
  <?php endif; ?>
</section>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
