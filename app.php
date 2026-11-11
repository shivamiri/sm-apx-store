<?php
require_once __DIR__ . '/common/config.php';

/** Validate source URLs without fetching or proxying any APK bytes. */
function downloadUrl(string $url): string
{
    $parts = parse_url(trim($url));
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https'
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
        return '';
    }
    $host = strtolower($parts['host'] ?? '');
    $path = $parts['path'] ?? '';
    if ($host === 'github.com'
        && preg_match('~^/[^/]+/[^/]+/releases/download/[^/]+/[^/]+\.apk$~i', $path)) {
        return trim($url);
    }
    if ($host === 'drive.google.com') {
        // Public Drive links can still show a quota or confirmation page.
        $fileId = '';
        if (preg_match('~^/file/d/([A-Za-z0-9_-]+)(?:/|$)~', $path, $m)) {
            $fileId = $m[1];
        } elseif ($path === '/uc' || $path === '/open') {
            parse_str($parts['query'] ?? '', $query);
            if (isset($query['id']) && is_string($query['id'])
                && preg_match('~^[A-Za-z0-9_-]+$~', $query['id'])) {
                $fileId = $query['id'];
            }
        }
        if ($fileId !== '') {
            return 'https://drive.google.com/uc?export=download&id=' . rawurlencode($fileId);
        }
    }
    return '';
}

if (!isset($_SESSION['download_csrf'])) {
    $_SESSION['download_csrf'] = bin2hex(random_bytes(32));
}

// This endpoint records button clicks only. It never serves an APK or redirects.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Cache-Control: no-store');
    $csrf = $_POST['csrf'] ?? '';
    $appId = $_POST['app_id'] ?? '';
    $requestId = $_POST['request_id'] ?? '';
    if (!is_string($csrf) || !hash_equals($_SESSION['download_csrf'], $csrf)) {
        jsonResponse(false, null, 'Please reload the page and try again.', 403);
    }
    if (!is_string($appId) || !ctype_digit($appId) || (int) $appId < 1
        || !is_string($requestId) || !preg_match('~^[a-f0-9]{32}$~', $requestId)
        || ($_POST['action'] ?? '') !== 'download_click') {
        jsonResponse(false, null, 'Invalid download request.', 400);
    }
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT apk_source_url FROM apps WHERE id = :id AND status = 'Live' AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => (int) $appId]);
        $source = $stmt->fetchColumn();
        if ($source === false || downloadUrl((string) $source) === '') {
            jsonResponse(false, null, 'Download is not available.', 404);
        }
        // Session writes are serialized by PHP. Repeated request IDs count once.
        $recent = $_SESSION['download_clicks'] ?? [];
        $now = time();
        foreach ($recent as $key => $item) {
            if ($item['at'] < $now - 600) { unset($recent[$key]); }
        }
        $key = $appId . ':' . $requestId;
        if (!isset($recent[$key])) {
            if (!checkRateLimit($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 'download_click', 20, 60)) {
                jsonResponse(false, null, 'Too many download clicks. Please wait a minute.', 429);
            }
            $stmt = $pdo->prepare("UPDATE apps SET download_count = download_count + 1 WHERE id = :id AND status = 'Live' AND deleted_at IS NULL");
            $stmt->execute([':id' => (int) $appId]);
            if ($stmt->rowCount() !== 1) {
                jsonResponse(false, null, 'App is no longer available.', 404);
            }
            $recent[$key] = ['at' => $now];
            $_SESSION['download_clicks'] = array_slice($recent, -100, null, true);
        }
        $stmt = $pdo->prepare("SELECT download_count FROM apps WHERE id = :id AND status = 'Live' AND deleted_at IS NULL");
        $stmt->execute([':id' => (int) $appId]);
        $count = $stmt->fetchColumn();
        jsonResponse(true, ['download_count' => (int) $count], null);
    } catch (Throwable $e) {
        jsonResponse(false, null, 'Could not record the download click.', 500);
    }
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}
header('Cache-Control: no-store');

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
                   a.download_count, a.rating_average, a.review_count, a.created_at, a.apk_source_url,
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
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/app.css?v=3.1.1">

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
  $download = downloadUrl((string) $app['apk_source_url']);
  $isDrive = $download !== '' && parse_url($download, PHP_URL_HOST) === 'drive.google.com';
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
    <div class="spec"><div class="spec-val"><span id="downloadCount"><?php echo e(formatCount((int) $app['download_count'])); ?></span></div><div class="caption">Downloads</div></div>
  </section>

  <?php if ($download !== ''): ?>
    <a class="btn btn-primary get-main download-link" id="downloadButton"
       href="<?php echo e($download); ?>" target="_self" rel="noopener noreferrer"
       data-app-id="<?php echo (int) $app['id']; ?>"
       data-csrf="<?php echo e($_SESSION['download_csrf']); ?>"
       data-endpoint="<?php echo e(SITE_URL . '/app.php'); ?>"
       aria-haspopup="dialog" aria-controls="downloadSheet">Download APK</a>
    <p class="caption download-note">External APK download. Confirm the download if your browser asks.</p>
    <?php if ($isDrive): ?>
      <p class="caption download-warning">Google Drive delivery is unverified. It may show a warning, quota limit, or confirmation page.</p>
    <?php endif; ?>
  <?php else: ?>
    <button class="btn btn-primary get-main" type="button" disabled>Download unavailable</button>
    <p class="caption download-warning">This app needs a valid public GitHub Release APK or Google Drive link.</p>
  <?php endif; ?>

  <div class="download-overlay" id="downloadOverlay" hidden>
    <section class="download-sheet" id="downloadSheet" role="dialog" aria-modal="true"
             aria-labelledby="downloadTitle" aria-describedby="downloadHelp" tabindex="-1">
      <div class="download-handle" aria-hidden="true"></div>
      <button class="download-close icon-btn" id="downloadClose" type="button" aria-label="Close download instructions">&#10005;</button>
      <p class="caption download-eyebrow">DIRECT APK DOWNLOAD</p>
      <h2 class="heading-2" id="downloadTitle">Check your browser downloads</h2>
      <p class="body-default" id="downloadHelp">The APK link has been opened. If your browser asks, confirm the download. When it finishes, open the file from your notification or Downloads to start installation.</p>
      <p class="caption download-safety">Only install apps you trust. Android may ask you to allow installs from this browser. This website cannot install an app automatically or confirm that a download finished.</p>
      <p class="caption download-status" id="downloadStatus" role="status" aria-live="polite"></p>
      <button class="btn btn-primary" id="downloadDone" type="button">Got it</button>
    </section>
  </div>
  <script src="<?php echo e(SITE_URL); ?>/assets/js/main.js" defer></script>

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
