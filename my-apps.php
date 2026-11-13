<?php
/**
 * Developer dashboard (Task 4.5).
 * Read-only: published apps plus outstanding and rejected submissions.
 * Approved queue history is omitted because apps holds the published version.
 */
require_once __DIR__ . '/common/config.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login.php?back=' . urlencode('/my-apps.php'));
    exit;
}

// Do not cache private developer data in shared or browser caches.
header('Cache-Control: no-store, private');

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function dashboardInitial($name): string
{
    $name = trim((string) $name);
    return preg_match('/^./us', $name, $match) ? strtoupper($match[0]) : 'A';
}

function dashboardIcon($url): string
{
    $url = trim((string) $url);
    $parts = parse_url($url);
    if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'https'
        || empty($parts['host']) || preg_match('/\s/', $url)) {
        return '';
    }
    return $url;
}

$items = [];
$loadError = false;
try {
    $pdo = getDbConnection();
    // Separate parameter names are required with native PDO prepares.
    // Every branch is owner-scoped. The target join also checks ownership.
    $stmt = $pdo->prepare("SELECT a.id, 'app' AS row_type, a.app_name, a.package_name,
                a.version_name, a.version_code, a.icon_url, a.status,
                '' AS submission_type, NULL AS target_name, NULL AS target_package,
                (SELECT s.admin_notes FROM app_submissions s
                 WHERE s.user_id = a.user_id AND s.status = 'rejected'
                   AND (s.app_id = a.id OR s.package_name = a.package_name)
                 ORDER BY s.updated_at DESC, s.id DESC LIMIT 1) AS admin_notes,
                a.updated_at AS changed_at
            FROM apps a
            WHERE a.user_id = :app_owner AND a.deleted_at IS NULL
            UNION ALL
            SELECT s.id, 'submission' AS row_type, s.app_name, s.package_name,
                s.version_name, s.version_code, s.icon_url, s.status,
                s.submission_type, a.app_name AS target_name,
                a.package_name AS target_package, s.admin_notes, s.updated_at AS changed_at
            FROM app_submissions s
            LEFT JOIN apps a ON a.id = s.app_id AND a.user_id = s.user_id
                AND a.deleted_at IS NULL
            WHERE s.user_id = :submission_owner
              AND s.status IN ('pending', 'in_review', 'rejected')
            ORDER BY changed_at DESC, row_type ASC, id DESC");
    $stmt->execute([
        ':app_owner' => (int) $_SESSION['user_id'],
        ':submission_owner' => (int) $_SESSION['user_id'],
    ]);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    // Never expose SQL, connection details or another developer's data.
    http_response_code(500);
    $loadError = true;
}

$statusMap = [
    'pending' => ['Pending', 'pending'],
    'in_review' => ['In Review', 'review'],
    'in review' => ['In Review', 'review'],
    'live' => ['Live', 'live'],
    'rejected' => ['Rejected', 'rejected'],
    'banned' => ['Banned', 'neutral'],
];
$page_title = 'My Apps - ' . APP_NAME;
require_once __DIR__ . '/common/header.php';
?>
<section class="my-apps" aria-labelledby="my-apps-title">
  <div class="my-apps-heading">
    <div>
      <h1 class="heading-1" id="my-apps-title">My Apps</h1>
      <p class="text-secondary">Your published apps and submission status, in one place.</p>
    </div>
    <?php if (!$loadError && $items): ?>
    <a class="btn btn-primary" href="<?php echo h(SITE_URL); ?>/upload.php">Submit an app</a>
    <?php endif; ?>
  </div>

  <?php if ($loadError): ?>
  <div class="my-apps-alert" role="alert">
    <h2 class="heading-3">Could not load your apps</h2>
    <p>Please try again. Your submissions have not been changed.</p>
    <a class="btn" href="<?php echo h(SITE_URL); ?>/my-apps.php">Try again</a>
  </div>
  <?php elseif (!$items): ?>
  <div class="card my-apps-empty">
    <h2 class="heading-2">No apps yet</h2>
    <p class="text-secondary">Submit your first app to start the review process.</p>
    <a class="btn btn-primary" href="<?php echo h(SITE_URL); ?>/upload.php">Submit your first app</a>
  </div>
  <?php else: ?>
  <p class="my-apps-help text-secondary">Updates appear separately while the current version stays live. Approved submissions are represented by the published app.</p>
  <ul class="my-apps-list" aria-label="Your apps and submissions">
    <?php foreach ($items as $item):
        $statusKey = strtolower(trim((string) $item['status']));
        $status = $statusMap[$statusKey] ?? ['Unknown', 'neutral'];
        $isUpdate = $item['row_type'] === 'submission' && $item['submission_type'] === 'update';
        $icon = dashboardIcon($item['icon_url']);
        $note = trim((string) $item['admin_notes']);
    ?>
    <li class="card my-apps-card">
      <div class="my-apps-row">
        <div class="my-apps-icon" aria-hidden="true">
          <span><?php echo h(dashboardInitial($item['app_name'])); ?></span>
          <?php if ($icon !== ''): ?>
          <img src="<?php echo h($icon); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
          <?php endif; ?>
        </div>
        <div class="my-apps-info">
          <div class="my-apps-name-row">
            <h2 class="heading-3"><?php echo h($item['app_name']); ?></h2>
            <?php if ($isUpdate): ?><span class="my-apps-update">Update</span><?php endif; ?>
          </div>
          <p class="my-apps-package"><?php echo h($item['package_name']); ?></p>
          <p class="my-apps-version">Version <?php echo h($item['version_name']); ?> <span class="text-secondary">(code <?php echo (int) $item['version_code']; ?>)</span></p>
          <?php if ($isUpdate): ?>
          <p class="my-apps-target text-secondary">Update for: <?php echo h($item['target_name'] ?: $item['package_name']); ?><?php if (!empty($item['target_package'])): ?> (<?php echo h($item['target_package']); ?>)<?php endif; ?></p>
          <?php endif; ?>
          <p class="my-apps-source text-secondary"><?php echo $item['row_type'] === 'app' ? 'Published app record' : 'Submission #' . (int) $item['id']; ?></p>
        </div>
        <span class="my-apps-status my-apps-status-<?php echo h($status[1]); ?>"><?php echo h($status[0]); ?></span>
      </div>
      <?php if ($statusKey === 'rejected'): ?>
      <details class="my-apps-rejection">
        <summary>Rejection note</summary>
        <p><?php echo h($note !== '' ? $note : 'No rejection note is available. Please contact the store administrator.'); ?></p>
      </details>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</section>
<script>
(function () {
  var icons = document.querySelectorAll('.my-apps-icon img');
  for (var i = 0; i < icons.length; i++) {
    icons[i].addEventListener('error', function () { this.remove(); });
    if (icons[i].complete && icons[i].naturalWidth === 0) {
      icons[i].remove();
    }
  }
})();
</script>
<?php require_once __DIR__ . '/common/bottom.php'; ?>