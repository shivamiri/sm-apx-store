</main>

<nav class="bottom-nav" aria-label="Main">
  <a href="<?php echo SITE_URL; ?>/" class="nav-item">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
    <span>Home</span>
  </a>
  <a href="<?php echo SITE_URL; ?>/search.php" class="nav-item">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="16.5" y1="16.5" x2="21" y2="21"/></svg>
    <span>Search</span>
  </a>
  <a href="<?php echo SITE_URL; ?>/categories.php" class="nav-item">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    <span>Categories</span>
  </a>
  <a href="<?php echo SITE_URL; ?>/account.php" class="nav-item">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>
    <span>Account</span>
  </a>
</nav>

<div id="toast" class="toast" role="status" aria-live="polite"></div>

<script>
  function showToast(msg) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(function () { t.classList.remove('show'); }, 2500);
  }
  document.getElementById('themeToggle').addEventListener('click', function () {
    var root = document.documentElement;
    var next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
    root.setAttribute('data-theme', next);
    localStorage.setItem('apx_theme', next);
    showToast(next === 'light' ? 'Light mode' : 'Dark mode');
  });
</script>
</body>
</html>
