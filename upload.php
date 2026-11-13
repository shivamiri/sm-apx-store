<?php
/**
 * Developer Submission Form for Apx (Tasks 4.2 + 4.3).
 *
 * Renders the "Submit New App" form: two columns on desktop, one column
 * on mobile, with client-side validation (Task 4.2) and APK link
 * verification with size auto-detection (Task 4.3, wired to
 * assets/js/uploader.js and ajax_check_size.php). POST processing
 * (saving the submission) arrives in Task 4.4, so the submit button
 * stays disabled.
 *
 * Access: signed-in users only. Guests are sent to login.php and come
 * back here after Google sign-in (login.php honours the "back" path).
 */

require_once __DIR__ . '/common/config.php';

// ---------- Auth guard (Google session from Task 4.1) ----------

if (empty($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login.php?back=' . urlencode('/upload.php'));
    exit;
}

// ---------- Categories for the dropdown ----------

$pdo = getDbConnection();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();

$page_title = 'Submit App - ' . APP_NAME;
require_once __DIR__ . '/common/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/upload.css">

<section class="upload-head">
  <h1 class="heading-1">Submit New App</h1>
  <p class="text-secondary">Share your app with Apx users. Submissions are reviewed before they go live.</p>
</section>

<form class="upload-form" id="uploadForm" action="upload.php" method="post" autocomplete="off" novalidate>
  <div class="upload-grid">

    <!-- Column 1: identity and artwork -->
    <div class="upload-col">
      <div class="field">
        <label class="field-label" for="app_name">App Name</label>
        <input class="input" id="app_name" name="app_name" type="text" maxlength="128" placeholder="My Cool App" required>
      </div>

      <div class="field">
        <label class="field-label" for="package_name">Package Name</label>
        <div class="input-with-indicator">
          <input class="input input-mono" id="package_name" name="package_name" type="text" maxlength="191" placeholder="com.example.myapp" required>
          <span class="pkg-indicator" id="pkgIndicator" aria-hidden="true"></span>
        </div>
        <p class="field-hint" id="pkgHint">Unique Android package ID, e.g. com.example.myapp</p>
      </div>

      <div class="field">
        <label class="field-label" for="category_id">Category</label>
        <select class="input" id="category_id" name="category_id" required>
          <option value="">Select a category</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label class="field-label" for="icon_url">Icon URL</label>
        <div class="url-with-preview">
          <input class="input" id="icon_url" name="icon_url" type="url" maxlength="512" placeholder="https://example.com/icon.png" required>
          <img class="icon-preview" id="iconPreview" alt="Icon preview" hidden>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="feature_graphic_url">Feature Graphic URL</label>
        <input class="input" id="feature_graphic_url" name="feature_graphic_url" type="url" maxlength="512" placeholder="https://example.com/feature.png" required>
        <div class="feature-preview" id="featurePreview" hidden>
          <img id="featurePreviewImg" alt="Feature graphic preview">
        </div>
      </div>

      <div class="field">
        <label class="field-label">Screenshots (1-8)</label>
        <div class="screenshot-inputs" id="screenshotInputs">
          <input class="input screenshot-url" name="screenshots[]" type="url" maxlength="512" placeholder="https://example.com/shot1.png">
        </div>
        <button class="btn btn-add-shot" id="addShotBtn" type="button">+ Add screenshot</button>
        <div class="shot-strip" id="shotStrip"></div>
      </div>

      <div class="field">
        <label class="field-label" for="youtube_url">YouTube URL <span class="text-tertiary">(optional)</span></label>
        <input class="input" id="youtube_url" name="youtube_url" type="url" maxlength="255" placeholder="https://www.youtube.com/watch?v=...">
      </div>
    </div>

    <!-- Column 2: binary and description -->
    <div class="upload-col">
      <div class="field">
        <label class="field-label" for="version_code">Version Code</label>
        <input class="input" id="version_code" name="version_code" type="number" min="1" step="1" placeholder="1" required>
        <p class="field-hint">Whole number, increase it with every update.</p>
      </div>

      <div class="field">
        <label class="field-label" for="version_name">Version Name</label>
        <input class="input" id="version_name" name="version_name" type="text" maxlength="32" placeholder="1.0.0" required>
      </div>

      <div class="field">
        <label class="field-label" for="apk_source_url">APK Source Link</label>
        <input class="input" id="apk_source_url" name="apk_source_url" type="url" placeholder="https://github.com/you/repo/releases/download/v1.0.0/app.apk" required>
        <div class="url-with-preview">
          <button class="btn" id="verifyLinkBtn" type="button">Verify Link &amp; Calculate Size</button>
          <input class="input" id="apkSizeDisplay" type="text" placeholder="Size appears here" readonly tabindex="-1" aria-label="Detected APK size">
        </div>
        <p class="field-hint" id="apkSizeStatus">Direct .apk link from a GitHub Release or a public Google Drive link.</p>
        <div class="field" id="manualSizeWrap" hidden>
          <label class="field-label" for="manual_size_mb">APK Size (MB)</label>
          <input class="input" id="manual_size_mb" name="manual_size_mb" type="number" min="0.1" max="4096" step="0.1" placeholder="e.g. 25.4">
          <p class="field-hint">Size detection failed - enter the APK size in megabytes (1 MB = 1024 KB).</p>
        </div>
        <!-- Filled by assets/js/uploader.js; Task 4.4 saves these into app_submissions. -->
        <input type="hidden" id="size_bytes" name="size_bytes" value="">
        <input type="hidden" id="size_formatted" name="size_formatted" value="">
      </div>

      <div class="banner">
        For APKs larger than 100 MB, please use GitHub Releases for fast, uninterrupted downloads.
      </div>

      <div class="field">
        <label class="field-label" for="description">Description</label>
        <textarea class="input" id="description" name="description" rows="10" placeholder="What does your app do? What is new in this version?" required></textarea>
      </div>
    </div>
  </div>

  <div class="upload-footer">
    <button class="btn btn-primary" id="submitBtn" type="submit" disabled title="Submission processing is added in the next build step">Submit for Review</button>
    <p class="text-tertiary footer-note">Submitting is enabled in the next build step. For now you can fill in and check the form.</p>
  </div>
</form>

<script>
(function () {
  // Package names look like com.example.myapp: two or more dot-separated
  // segments, each starting with a letter, then letters/digits/underscore.
  var PKG_RE = /^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)+$/;
  var MAX_SHOTS = 8;

  var pkg = document.getElementById('package_name');
  var pkgIndicator = document.getElementById('pkgIndicator');
  var pkgHint = document.getElementById('pkgHint');
  var pkgDefaultHint = pkgHint.textContent;

  pkg.addEventListener('input', function () {
    var v = pkg.value.trim();
    if (v === '') {
      pkgIndicator.textContent = '';
      pkgIndicator.className = 'pkg-indicator';
      pkgHint.textContent = pkgDefaultHint;
      pkgHint.classList.remove('field-hint-error');
      return;
    }
    if (PKG_RE.test(v)) {
      pkgIndicator.textContent = '\u2713';
      pkgIndicator.className = 'pkg-indicator ok';
      pkgHint.textContent = 'Looks good.';
      pkgHint.classList.remove('field-hint-error');
    } else {
      pkgIndicator.textContent = '\u2717';
      pkgIndicator.className = 'pkg-indicator bad';
      pkgHint.textContent = 'Use segments like com.example.myapp (letters, digits, underscore; no spaces).';
      pkgHint.classList.add('field-hint-error');
    }
  });

  // Live image previews: the preview stays hidden until the URL loads.
  function wirePreview(inputId, img, showEl, onInput) {
    var input = document.getElementById(inputId);
    var timer = null;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        var url = input.value.trim();
        if (onInput) { onInput(url); }
        if (url === '') {
          showEl.hidden = true;
          img.removeAttribute('src');
          return;
        }
        img.src = url;
      }, 400);
    });
    img.addEventListener('load', function () { showEl.hidden = false; });
    img.addEventListener('error', function () { showEl.hidden = true; });
  }

  wirePreview('icon_url', document.getElementById('iconPreview'), document.getElementById('iconPreview'));
  wirePreview('feature_graphic_url', document.getElementById('featurePreviewImg'), document.getElementById('featurePreview'));

  // Screenshots: add up to 8 URL inputs, with a thumbnail strip.
  var shotInputs = document.getElementById('screenshotInputs');
  var addShotBtn = document.getElementById('addShotBtn');
  var shotStrip = document.getElementById('shotStrip');

  function shotCount() {
    return shotInputs.querySelectorAll('.screenshot-url').length;
  }

  function refreshShotButtons() {
    addShotBtn.disabled = shotCount() >= MAX_SHOTS;
    shotInputs.querySelectorAll('.shot-remove').forEach(function (btn) {
      btn.hidden = shotCount() <= 1;
    });
  }

  function refreshShotStrip() {
    shotStrip.textContent = '';
    shotInputs.querySelectorAll('.screenshot-url').forEach(function (input) {
      var url = input.value.trim();
      if (url === '') { return; }
      var img = document.createElement('img');
      img.className = 'shot-thumb';
      img.alt = 'Screenshot preview';
      img.loading = 'lazy';
      img.src = url;
      img.addEventListener('error', function () { img.remove(); });
      shotStrip.appendChild(img);
    });
  }

  function addShotInput(value) {
    var row = document.createElement('div');
    row.className = 'shot-row';

    var input = document.createElement('input');
    input.className = 'input screenshot-url';
    input.name = 'screenshots[]';
    input.type = 'url';
    input.maxLength = 512;
    input.placeholder = 'https://example.com/shot' + (shotCount() + 1) + '.png';
    if (value) { input.value = value; }

    var removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'icon-btn shot-remove';
    removeBtn.setAttribute('aria-label', 'Remove screenshot');
    removeBtn.textContent = '\u00D7';
    removeBtn.addEventListener('click', function () {
      row.remove();
      refreshShotButtons();
      refreshShotStrip();
    });

    var timer = null;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(refreshShotStrip, 400);
    });

    row.appendChild(input);
    row.appendChild(removeBtn);
    shotInputs.appendChild(row);
    refreshShotButtons();
  }

  // The first screenshot field is in the HTML; rebuild it as a row with a remove button.
  (function () {
    var first = shotInputs.querySelector('.screenshot-url');
    first.remove();
    addShotInput('');
  })();

  addShotBtn.addEventListener('click', function () {
    if (shotCount() < MAX_SHOTS) { addShotInput(''); }
  });

  refreshShotButtons();
})();
</script>

<script src="<?php echo SITE_URL; ?>/assets/js/uploader.js"></script>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
