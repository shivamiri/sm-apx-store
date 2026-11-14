<?php
/**
 * Task 5.2: shared admin layout header + authentication gatekeeper.
 * Every protected admin page includes this file first:
 *   $admin_page = 'dashboard'; $page_title = 'Dashboard';
 *   require_once __DIR__ . '/common/header.php';
 * It blocks every visitor without an admin session, renders the topbar,
 * the left sidebar navigation and the live metric cards, then opens the
 * content area. admin/common/bottom.php closes the page.
 */
require_once __DIR__ . '/../../common/config.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

// Gatekeeper: without an admin session nothing renders - straight to login.
if (($_SESSION['admin_logged_in'] ?? false) !== true || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}

if (!function_exists('adminEscape')) {
    function adminEscape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$admin_page = isset($admin_page) ? $admin_page : '';
$page_title = isset($page_title) ? $page_title : 'Admin';
$admin_show_metrics = isset($admin_show_metrics) ? (bool) $admin_show_metrics : true;

// Live metric counts from MySQL. A query problem must never break an admin
// page, so a failure leaves a count as null and the card shows a dash.
$metrics = [
    'pending_submissions' => null,
    'total_apps'          => null,
    'live_apps'           => null,
    'banned_users'        => null,
];
try {
    $pdo = getDbConnection();
    $metrics['pending_submissions'] = (int) $pdo->query("SELECT COUNT(*) FROM app_submissions WHERE status = 'pending'")->fetchColumn();
    $metrics['total_apps']          = (int) $pdo->query('SELECT COUNT(*) FROM apps')->fetchColumn();
    $metrics['live_apps']           = (int) $pdo->query("SELECT COUNT(*) FROM apps WHERE status = 'Live'")->fetchColumn();
    $metrics['banned_users']        = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_banned = 1')->fetchColumn();
} catch (Throwable $e) {
    // Cards render "-" below; the rest of the page still works.
}

// Sidebar items. Hrefs point at the pages Tasks 5.3-5.7 will create.
// "Submissions Queue" anchors to the queue section on the dashboard (5.3).
$admin_nav = [
    'dashboard' => [
        'Dashboard', 'index.php',
        '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>',
    ],
    'submissions' => [
        'Submissions Queue', 'index.php#queue',
        '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
    ],
    'apps' => [
        'All Apps', 'apps.php',
        '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    ],
    'users' => [
        'Users', 'users.php',
        '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    ],
    'config' => [
        'System Config', 'config.php',
        '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>',
    ],
];

// Renders one metric card; null counts show a dash instead of breaking.
function adminMetricCard(string $label, ?int $value, bool $alert = false): void
{
    $classes = 'admin-metric' . ($alert && $value !== null && $value > 0 ? ' is-alert' : '');
    echo '<div class="' . $classes . '">';
    echo '<span class="admin-metric-value">' . ($value === null ? '&ndash;' : number_format($value)) . '</span>';
    echo '<span class="admin-metric-label">' . adminEscape($label) . '</span>';
    echo '</div>';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo adminEscape($page_title); ?> | <?php echo adminEscape(APP_NAME); ?></title>
  <script>
    (function () {
      try {
        var theme = localStorage.getItem('apx_theme');
        if (theme === 'light' || theme === 'dark') document.documentElement.setAttribute('data-theme', theme);
      } catch (e) {}
    })();
  </script>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-page">
  <header class="admin-topbar">
    <a class="admin-brand" href="index.php"><span class="admin-mark" aria-hidden="true">A</span><span><?php echo adminEscape(APP_SHORT_NAME); ?> <span class="admin-tag">ADMIN</span></span></a>
    <div class="admin-topbar-right">
      <span class="admin-user">Signed in as <strong><?php echo adminEscape($_SESSION['admin_username'] ?? 'Admin'); ?></strong></span>
      <button class="admin-theme" id="adminTheme" type="button" aria-label="Toggle light or dark theme">Theme</button>
    </div>
  </header>
  <div class="admin-shell">
    <aside class="admin-sidebar" aria-label="Admin navigation">
      <nav class="admin-nav">
        <?php foreach ($admin_nav as $key => $item): ?>
        <a class="admin-nav-item" href="<?php echo adminEscape($item[1]); ?>"<?php if ($admin_page === $key): ?> aria-current="page"<?php endif; ?>><?php echo $item[2]; ?><span><?php echo adminEscape($item[0]); ?></span></a>
        <?php endforeach; ?>
        <form action="logout.php" method="post" class="admin-nav-form">
          <input type="hidden" name="csrf_token" value="<?php echo adminEscape($_SESSION['admin_csrf']); ?>">
          <button class="admin-nav-item" type="submit"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg><span>Logout</span></button>
        </form>
      </nav>
    </aside>
    <main class="admin-content">
      <?php if ($admin_show_metrics): ?>
      <section class="admin-metrics" aria-label="Store metrics">
        <?php
        adminMetricCard('Pending Submissions', $metrics['pending_submissions'], true);
        adminMetricCard('Total Apps', $metrics['total_apps']);
        adminMetricCard('Live Apps', $metrics['live_apps']);
        adminMetricCard('Banned Users', $metrics['banned_users']);
        ?>
      </section>
      <?php endif; ?>
