<?php
/** Task 5.1 PLACEHOLDER ONLY. Tasks 5.2/5.3 will build the real dashboard. */
require_once __DIR__ . '/../common/config.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if (($_SESSION['admin_logged_in'] ?? false) !== true || empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
function adminEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard | <?php echo adminEscape(APP_NAME); ?></title>
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
    <button class="admin-theme" id="adminTheme" type="button" aria-label="Toggle light or dark theme">Theme</button>
  </header>
  <main class="admin-main">
    <section class="admin-card" aria-labelledby="dashboardTitle">
      <p class="admin-eyebrow">AUTHENTICATED</p>
      <h1 id="dashboardTitle">Admin Dashboard</h1>
      <p class="admin-intro">Signed in as <strong><?php echo adminEscape($_SESSION['admin_username'] ?? 'Admin'); ?></strong>.</p>
      <div class="admin-placeholder">
        <strong>Task 5.1 placeholder</strong>
        <p>Admin sign-in is ready. The dashboard layout and moderation tools will be added in Tasks 5.2 and 5.3.</p>
      </div>
      <form action="logout.php" method="post" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?php echo adminEscape($_SESSION['admin_csrf']); ?>">
        <button class="btn admin-primary" type="submit">Sign out of admin</button>
      </form>
      <p class="admin-note">Signing out here will not sign you out of your store account.</p>
      <a class="admin-back" href="../index.php">Back to store</a>
    </section>
  </main>
  <script>
    document.getElementById('adminTheme').addEventListener('click', function () {
      var root = document.documentElement;
      var theme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', theme);
      try { localStorage.setItem('apx_theme', theme); } catch (e) {}
    });
  </script>
</body>
</html>