<?php
require_once __DIR__ . '/config.php';
$page_title = isset($page_title) ? $page_title : APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <script>
    (function () {
      var t = localStorage.getItem('apx_theme');
      if (t === 'light' || t === 'dark') {
        document.documentElement.setAttribute('data-theme', t);
      }
    })();
  </script>
  <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/layout.css">
</head>
<body>
<header class="top-bar">
  <a class="brand" href="<?php echo SITE_URL; ?>/">
    <span class="brand-mark">A</span>
    <span class="brand-name"><?php echo htmlspecialchars(APP_SHORT_NAME); ?></span>
  </a>
  <div class="top-actions">
    <a class="icon-btn" href="<?php echo SITE_URL; ?>/search.php" aria-label="Search">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg>
    </a>
    <button class="icon-btn" id="themeToggle" type="button" aria-label="Toggle theme">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
    </button>
  </div>
</header>
<main class="page container">
