<?php
require_once __DIR__ . '/common/config.php';

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$page_title = 'Search - ' . APP_NAME;
require_once __DIR__ . '/common/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/search.css">

<form class="search-bar" id="searchForm" action="search.php" method="get" autocomplete="off">
  <input class="input" id="q" name="q" type="search" placeholder="Search apps" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" maxlength="100" autofocus>
</form>

<div class="banner" id="banner" hidden></div>
<div class="result-list" id="results"></div>
<p class="text-secondary hint" id="hint">Type an app name to search.</p>

<script>
(function () {
  var API = <?php echo json_encode(SITE_URL . '/api/search.php'); ?>;
  var input = document.getElementById('q');
  var form = document.getElementById('searchForm');
  var results = document.getElementById('results');
  var banner = document.getElementById('banner');
  var hint = document.getElementById('hint');
  var timer = null, seq = 0;

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined) { n.textContent = text; }
    return n;
  }

  function card(app) {
    var a = el('a', 'result-card');
    a.href = 'app.php?id=' + encodeURIComponent(app.id);
    var img = el('img', 'result-icon');
    img.src = app.icon_url; img.alt = ''; img.loading = 'lazy';
    var info = el('div', 'result-info');
    info.appendChild(el('div', 'heading-3', app.app_name));
    var meta = app.category + ' \u00B7 ' + app.size_formatted + ' \u00B7 \u2605 ' + Number(app.rating_average).toFixed(1);
    info.appendChild(el('div', 'caption', meta));
    if (app.recommendation_reason) {
      info.appendChild(el('div', 'reason', app.recommendation_reason));
    }
    a.appendChild(img); a.appendChild(info);
    return a;
  }

  function clearAll() {
    results.textContent = '';
    banner.hidden = true;
  }

  function run() {
    var q = input.value.trim();
    var mine = ++seq;
    if (q === '') {
      clearAll();
      hint.hidden = false;
      hint.textContent = 'Type an app name to search.';
      return;
    }
    hint.hidden = false;
    hint.textContent = 'Searching...';
    fetch(API + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (mine !== seq) { return; }
        clearAll();
        if (!j.success) {
          hint.textContent = 'Search failed. Please try again.';
          return;
        }
        hint.hidden = true;
        var d = j.data;
        if (d.fallback_applied) {
          banner.textContent = d.fallback_message;
          banner.hidden = false;
        }
        d.results.forEach(function (app) { results.appendChild(card(app)); });
        if (!d.results.length) {
          hint.hidden = false;
          hint.textContent = 'No apps found.';
        }
      })
      .catch(function () {
        if (mine !== seq) { return; }
        hint.hidden = false;
        hint.textContent = 'Could not reach the server.';
      });
  }

  input.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(run, 400);
    if (history.replaceState) {
      history.replaceState(null, '', input.value.trim() ? 'search.php?q=' + encodeURIComponent(input.value.trim()) : 'search.php');
    }
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearTimeout(timer);
    run();
  });

  run();
})();
</script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
