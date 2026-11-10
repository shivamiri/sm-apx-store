<?php
require_once __DIR__ . '/common/config.php';

function e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function youtubeId(?string $url): string
{
    if (!$url) { return ''; }
    if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', $url, $m)) {
        return $m[1];
    }
    return '';
}

function formatCount(int $n): string
{
    if ($n >= 1000000) { return rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . 'M'; }
    if ($n >= 1000)    { return rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K'; }
    return (string) $n;
}

function appAge(string $createdAt): string
{
    $days = (int) floor((time() - strtotime($createdAt)) / 86400);
    if ($days < 1)   { return 'New'; }
    if ($days < 30)  { return $days . 'd'; }
    if ($days < 365) { return floor($days / 30) . 'mo'; }
    return floor($days / 365) . 'y';
}

$id  = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$pkg = isset($_GET['package_name']) ? trim((string) $_GET['package_name']) : '';

$app = null;
$screens = [];
$reviews = [];

if (($id !== '' && ctype_digit($id)) || ($id === '' && $pkg !== '' && strlen($pkg) <= 191)) {
    $pdo = getDbConnection();
    $sql = "SELECT a.id, a.app_name, a.package_name, a.version_name, a.size_formatted,
                   a.icon_url, a.youtube_url, a.description, a.is_admin_app,
                   a.download_count, a.rating_average, a.review_count, a.created_at,
                   c.name AS category_name, u.display_name AS developer_name
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
    $app = $stmt->fetch() ?: null;

    if ($app) {
        $s = $pdo->prepare('SELECT screenshot_url FROM app_screenshots WHERE app_id = :id ORDER BY sort_order ASC, id ASC LIMIT 8');
        $s->execute([':id' => (int) $app['id']]);
        $screens = $s->fetchAll();

        $r = $pdo->prepare(
            'SELECT r.rating, r.comment, r.created_at, u.display_name, u.avatar_url
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.app_id = :id AND r.deleted_at IS NULL AND u.deleted_at IS NULL
             ORDER BY r.created_at DESC, r.id DESC LIMIT 20'
        );
        $r->execute([':id' => (int) $app['id']]);
        $reviews = $r->fetchAll();
    }
}

if (!$app) {
    http_response_code(404);
}

$page_title = $app ? $app['app_name'] . ' - ' . APP_NAME : 'App not found - ' . APP_NAME;
require_once __DIR__ . '/common/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/app.css">

<?php if (!$app): ?>
  <section class="notfound">
    <div class="heading-1">App not found</div>
    <p class="text-secondary">This app does not exist or is not available right now.</p>
    <a class="btn btn-primary" href="<?php echo SITE_URL; ?>/">Back to Home</a>
  </section>
<?php else:
  $ytId = youtubeId($app['youtube_url']);
  $desc = (string) $app['description'];
  $long = strlen($desc) > 220;
?>
  <section class="app-head">
    <img class="app-head-icon" src="<?php echo e($app['icon_url']); ?>" alt="">
    <div class="app-head-info">
      <h1 class="heading-1"><?php echo e($app['app_name']); ?></h1>
      <div class="body-medium dev-name"><?php echo e($app['developer_name'] ?: 'Apx'); ?><?php if ($app['is_admin_app']): ?> <span class="pill pill-admin">Official</span><?php endif; ?></div>
      <div class="caption"><?php echo e($app['category_name']); ?> &middot; v<?php echo e($app['version_name']); ?></div>
    </div>
  </section>

  <section class="spec-strip">
    <div class="spec"><div class="spec-val"><?php echo number_format((float) $app['rating_average'], 1); ?> &#9733;</div><div class="caption"><?php echo (int) $app['review_count']; ?> reviews</div></div>
    <div class="spec"><div class="spec-val"><?php echo e($app['size_formatted']); ?></div><div class="caption">Size</div></div>
    <div class="spec"><div class="spec-val"><?php echo e(appAge($app['created_at'])); ?></div><div class="caption">Age</div></div>
    <div class="spec"><div class="spec-val"><?php echo e(formatCount((int) $app['download_count'])); ?></div><div class="caption">Downloads</div></div>
  </section>

  <button class="btn btn-primary get-main" type="button" disabled title="Download is added in Phase 3">GET</button>

  <?php if ($screens): ?>
  <section class="gallery">
    <?php foreach ($screens as $sc): ?>
      <img class="shot" src="<?php echo e($sc['screenshot_url']); ?>" alt="Screenshot" loading="lazy">
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <section class="block">
    <h2 class="heading-2">About this app</h2>
    <div class="desc<?php echo $long ? ' clamp' : ''; ?>" id="desc"><?php echo nl2br(e($desc)); ?></div>
    <?php if ($long): ?>
      <button class="link-btn" id="descToggle" type="button">Read more</button>
    <?php endif; ?>
  </section>

  <?php if ($ytId): ?>
  <section class="block">
    <h2 class="heading-2">Video</h2>
    <div class="video">
      <iframe src="https://www.youtube-nocookie.com/embed/<?php echo e($ytId); ?>" title="App video" allowfullscreen loading="lazy"></iframe>
    </div>
  </section>
  <?php endif; ?>

  <section class="block">
    <h2 class="heading-2">Reviews</h2>
    <?php if (!$reviews): ?>
      <p class="text-secondary">No reviews yet.</p>
    <?php else: foreach ($reviews as $rv): ?>
      <div class="review">
        <div class="review-top">
          <?php if ($rv['avatar_url']): ?><img class="avatar" src="<?php echo e($rv['avatar_url']); ?>" alt=""><?php else: ?><span class="avatar avatar-ph"><?php echo e(mb_strtoupper(mb_substr($rv['display_name'], 0, 1))); ?></span><?php endif; ?>
          <div>
            <div class="body-medium"><?php echo e($rv['display_name']); ?></div>
            <div class="stars"><?php echo str_repeat('&#9733;', (int) $rv['rating']) . str_repeat('&#9734;', 5 - (int) $rv['rating']); ?></div>
          </div>
        </div>
        <p class="body-default"><?php echo nl2br(e($rv['comment'])); ?></p>
      </div>
    <?php endforeach; endif; ?>
  </section>

  <script>
    (function () {
      var b = document.getElementById('descToggle');
      if (!b) { return; }
      b.addEventListener('click', function () {
        var d = document.getElementById('desc');
        var open = d.classList.toggle('clamp') === false;
        b.textContent = open ? 'Show less' : 'Read more';
      });
    })();
  </script>
<?php endif; ?>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
