<?php
/**
 * Developer Submission Form for Apx (Tasks 4.2 + 4.3).
 *
 * Renders the "Submit New App" form: two columns on desktop, one column
 * on mobile, with client-side validation (Task 4.2) and APK link
 * verification with size auto-detection (Task 4.3, wired to
 * assets/js/uploader.js and ajax_check_size.php). Task 4.4 adds the POST
 * processing: full server-side validation, then one row in
 * app_submissions (status 'pending'):
 *   - brand new package        -> submission_type = 'new'
 *   - Live app owned by user   -> submission_type = 'update', app_id = apps.id,
 *                                 version_code must be higher than the live one.
 * The live row in `apps` is never touched here; the admin panel applies
 * approved submissions later (zero-downtime updates).
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


// ---------- Helpers ----------

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Returns true for a well-formed https URL (host present, no spaces). */
function isHttpsUrl(string $url, int $maxLen): bool
{
    if ($url === '' || strlen($url) > $maxLen || preg_match('/\s/', $url)) {
        return false;
    }
    $parts = parse_url($url);
    return $parts !== false
        && isset($parts['scheme'], $parts['host'])
        && strtolower($parts['scheme']) === 'https'
        && $parts['host'] !== '';
}

/**
 * Same hosts as ajax_check_size.php: GitHub Release assets and Google
 * Drive share links. Returns 'github', 'gdrive' or null.
 */
function apkSourceType(string $url): ?string
{
    if (!isHttpsUrl($url, 512)) {
        return null;
    }
    $parts = parse_url($url);
    $host = strtolower($parts['host']);
    $path = $parts['path'] ?? '';
    if ($host === 'github.com' && strpos($path, '/releases/download/') !== false) {
        return 'github';
    }
    if ($host === 'drive.google.com') {
        if (preg_match('#^/file/d/[A-Za-z0-9_-]{10,}#', $path)) {
            return 'gdrive';
        }
        parse_str($parts['query'] ?? '', $q);
        if (!empty($q['id']) && is_string($q['id']) && preg_match('/^[A-Za-z0-9_-]{10,}$/', $q['id'])) {
            return 'gdrive';
        }
    }
    return null;
}

function isYoutubeUrl(string $url): bool
{
    if (!isHttpsUrl($url, 255)) {
        return false;
    }
    $host = strtolower(parse_url($url, PHP_URL_HOST));
    return in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true);
}

// ---------- Form state ----------

if (empty($_SESSION['upload_csrf'])) {
    $_SESSION['upload_csrf'] = bin2hex(random_bytes(16));
}

$fields = [
    'app_name' => '', 'package_name' => '', 'category_id' => '', 'icon_url' => '',
    'feature_graphic_url' => '', 'youtube_url' => '', 'version_code' => '',
    'version_name' => '', 'apk_source_url' => '', 'description' => '',
    'size_bytes' => '', 'size_formatted' => '', 'manual_size_mb' => '',
];
$oldShots = [];
$errors = [];
$success = null;

// Success state after the redirect (Post/Redirect/Get: a page refresh
// can never insert the same submission twice).
if (!empty($_SESSION['upload_flash'])) {
    $success = $_SESSION['upload_flash'];
    unset($_SESSION['upload_flash']);
}

$pdo = getDbConnection();
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY sort_order ASC, id ASC')->fetchAll();

// ---------- POST processing (Task 4.4) ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int) $_SESSION['user_id'];

    foreach ($fields as $key => $_) {
        $fields[$key] = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
    }
    if (isset($_POST['screenshots']) && is_array($_POST['screenshots'])) {
        foreach ($_POST['screenshots'] as $shot) {
            if (is_string($shot) && trim($shot) !== '') {
                $oldShots[] = trim($shot);
            }
        }
    }

    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!hash_equals($_SESSION['upload_csrf'], $token)) {
        $errors[] = 'Your session form expired. Please submit again.';
    } elseif (!checkRateLimit('u' . $userId, 'upload_submit', 5, 60)) {
        $errors[] = 'Too many submissions. Wait a minute and try again.';
    } else {
        // ----- Field validation (never trust the browser) -----

        $nameLen = function_exists('mb_strlen') ? mb_strlen($fields['app_name'], 'UTF-8') : strlen($fields['app_name']);
        if ($nameLen < 3 || $nameLen > 50) {
            $errors[] = 'App name must be 3 to 50 characters.';
        }

        $pkgOk = strlen($fields['package_name']) <= 191
            && preg_match('/^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)+$/', $fields['package_name']);
        if (!$pkgOk) {
            $errors[] = 'Package name must look like com.example.myapp.';
        }

        $categoryId = 0;
        if (ctype_digit($fields['category_id'])) {
            $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = :id');
            $stmt->execute([':id' => (int) $fields['category_id']]);
            $categoryId = (int) $stmt->fetchColumn();
        }
        if ($categoryId < 1) {
            $errors[] = 'Choose a valid category.';
        }

        if (!isHttpsUrl($fields['icon_url'], 512)) {
            $errors[] = 'Icon URL must be a valid https link.';
        }
        if (!isHttpsUrl($fields['feature_graphic_url'], 512)) {
            $errors[] = 'Feature graphic URL must be a valid https link.';
        }

        $shots = array_values(array_unique($oldShots));
        if (count($shots) < 1 || count($shots) > 8) {
            $errors[] = 'Add between 1 and 8 different screenshot URLs.';
        } else {
            foreach ($shots as $shot) {
                if (!isHttpsUrl($shot, 512)) {
                    $errors[] = 'Every screenshot URL must be a valid https link.';
                    break;
                }
            }
        }

        if ($fields['youtube_url'] !== '' && !isYoutubeUrl($fields['youtube_url'])) {
            $errors[] = 'YouTube URL must be a youtube.com or youtu.be https link (or leave it empty).';
        }

        $versionCode = 0;
        if (ctype_digit($fields['version_code']) && strlen($fields['version_code']) <= 10
            && (int) $fields['version_code'] >= 1 && (int) $fields['version_code'] <= 2100000000) {
            $versionCode = (int) $fields['version_code'];
        } else {
            $errors[] = 'Version code must be a whole number of 1 or more.';
        }

        $vnLen = strlen($fields['version_name']);
        if ($vnLen < 1 || $vnLen > 32 || preg_match('/[\x00-\x1F]/', $fields['version_name'])) {
            $errors[] = 'Version name is required (up to 32 characters).';
        }

        $sourceType = apkSourceType($fields['apk_source_url']);
        if ($sourceType === null) {
            $errors[] = 'APK link must be a GitHub Release download link or a Google Drive share link (https).';
        }

        // Size: detected bytes (hidden field) or the manual MB value.
        $sizeBytes = 0;
        if (ctype_digit($fields['size_bytes']) && strlen($fields['size_bytes']) <= 12) {
            $sizeBytes = (int) $fields['size_bytes'];
        }
        if ($sizeBytes < 1 && $fields['manual_size_mb'] !== '' && is_numeric($fields['manual_size_mb'])) {
            $mb = (float) $fields['manual_size_mb'];
            if ($mb >= 0.1 && $mb <= 4096) {
                $sizeBytes = (int) round($mb * 1048576);
            }
        }
        if ($sizeBytes < 1 || $sizeBytes > 4096 * 1048576) {
            $errors[] = 'APK size is missing. Verify the link, or enter the size in MB.';
        }
        $sizeFormatted = number_format($sizeBytes / 1048576, 1) . ' MB';

        $descLen = function_exists('mb_strlen') ? mb_strlen($fields['description'], 'UTF-8') : strlen($fields['description']);
        if ($descLen < 10 || $descLen > 10000) {
            $errors[] = 'Description must be 10 to 10000 characters.';
        }

        // ----- New vs update decision + insert -----

        if (!$errors) {
            try {
                // The screenshots column is required by this build step;
                // see the SQL in the error text if it is missing.
                $colStmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'app_submissions' AND COLUMN_NAME = 'screenshots'");
                if ((int) $colStmt->fetchColumn() < 1) {
                    $errors[] = 'Setup needed: table app_submissions has no "screenshots" column. Run in phpMyAdmin: '
                        . 'ALTER TABLE app_submissions ADD COLUMN screenshots TEXT NULL AFTER feature_graphic_url;';
                } else {
                    $stmt = $pdo->prepare('SELECT is_banned, deleted_at FROM users WHERE id = :id');
                    $stmt->execute([':id' => $userId]);
                    $me = $stmt->fetch();
                    if (!$me || (int) $me['is_banned'] === 1 || $me['deleted_at'] !== null) {
                        $errors[] = 'This account cannot submit apps.';
                    }
                }

                if (!$errors) {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('SELECT id, user_id, version_code, status, deleted_at
                        FROM apps WHERE package_name = :p FOR UPDATE');
                    $stmt->execute([':p' => $fields['package_name']]);
                    $existing = $stmt->fetch();

                    $type = 'new';
                    $appId = null;

                    if ($existing) {
                        if ($existing['user_id'] === null || (int) $existing['user_id'] !== $userId) {
                            $errors[] = 'This package name is already registered by another developer.';
                        } elseif ($existing['status'] !== 'Live' || $existing['deleted_at'] !== null) {
                            $errors[] = 'This app is not Live right now, so it cannot receive an update.';
                        } elseif ($versionCode <= (int) $existing['version_code']) {
                            $errors[] = 'Version code must be higher than the live version ('
                                . (int) $existing['version_code'] . ').';
                        } else {
                            $type = 'update';
                            $appId = (int) $existing['id'];
                        }
                    }

                    if (!$errors) {
                        // One open submission per package keeps the review queue clean.
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_submissions
                            WHERE package_name = :p AND status IN ('pending', 'in_review')");
                        $stmt->execute([':p' => $fields['package_name']]);
                        if ((int) $stmt->fetchColumn() > 0) {
                            $errors[] = 'A submission for this package is already waiting for review.';
                        }
                    }

                    if ($errors) {
                        $pdo->rollBack();
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO app_submissions
                            (app_id, user_id, submission_type, package_name, app_name, category_id,
                             version_code, version_name, apk_source_type, apk_source_url,
                             size_bytes, size_formatted, icon_url, feature_graphic_url, screenshots,
                             description, youtube_url, status)
                            VALUES
                            (:app_id, :user_id, :type, :package_name, :app_name, :category_id,
                             :version_code, :version_name, :source_type, :source_url,
                             :size_bytes, :size_formatted, :icon_url, :feature_url, :screenshots,
                             :description, :youtube_url, \'pending\')');
                        $stmt->execute([
                            ':app_id'         => $appId,
                            ':user_id'        => $userId,
                            ':type'           => $type,
                            ':package_name'   => $fields['package_name'],
                            ':app_name'       => $fields['app_name'],
                            ':category_id'    => $categoryId,
                            ':version_code'   => $versionCode,
                            ':version_name'   => $fields['version_name'],
                            ':source_type'    => $sourceType,
                            ':source_url'     => $fields['apk_source_url'],
                            ':size_bytes'     => $sizeBytes,
                            ':size_formatted' => $sizeFormatted,
                            ':icon_url'       => $fields['icon_url'],
                            ':feature_url'    => $fields['feature_graphic_url'],
                            ':screenshots'    => json_encode($shots, JSON_UNESCAPED_SLASHES),
                            ':description'    => $fields['description'],
                            ':youtube_url'    => $fields['youtube_url'] !== '' ? $fields['youtube_url'] : null,
                        ]);
                        $submissionId = (int) $pdo->lastInsertId();
                        $pdo->commit();

                        $_SESSION['upload_flash'] = [
                            'id'           => $submissionId,
                            'type'         => $type,
                            'app_name'     => $fields['app_name'],
                            'package_name' => $fields['package_name'],
                            'version_name' => $fields['version_name'],
                            'version_code' => $versionCode,
                        ];
                        header('Location: ' . SITE_URL . '/upload.php');
                        exit;
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('upload.php submission failed: ' . $e->getMessage());
                $errors[] = 'Could not save your submission. Please try again.';
            }
        }
    }
}

$showManual = ($fields['manual_size_mb'] !== '') || (bool) array_filter($errors, function ($e) {
    return strpos($e, 'APK size') !== false;
});

$page_title = 'Submit App - ' . APP_NAME;
require_once __DIR__ . '/common/header.php';
?>
<link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/upload.css">

<section class="upload-head">
  <h1 class="heading-1">Submit New App</h1>
  <p class="text-secondary">Share your app with Apx users. Submissions are reviewed before they go live.</p>
</section>

<?php if ($success): ?>
  <div class="banner" role="status">
    <strong>Submission received - pending review.</strong><br>
    <?php echo h($success['app_name']); ?> (<?php echo h($success['package_name']); ?>),
    version <?php echo h($success['version_name']); ?> (code <?php echo (int) $success['version_code']; ?>).<br>
    Type: <?php echo $success['type'] === 'update' ? 'Update to your live app (the current version stays live until approved)' : 'New app'; ?>.
    Submission #<?php echo (int) $success['id']; ?>.
  </div>
  <p><a class="btn btn-primary" href="<?php echo SITE_URL; ?>/upload.php">Submit another app</a></p>
<?php else: ?>

<?php if ($errors): ?>
  <div class="banner" role="alert">
    <strong>Please fix the following:</strong>
    <ul>
      <?php foreach ($errors as $err): ?><li><?php echo h($err); ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<form class="upload-form" id="uploadForm" action="upload.php" method="post" autocomplete="off" novalidate>
  <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['upload_csrf']); ?>">
  <div class="upload-grid">

    <!-- Column 1: identity and artwork -->
    <div class="upload-col">
      <div class="field">
        <label class="field-label" for="app_name">App Name</label>
        <input class="input" id="app_name" name="app_name" value="<?php echo h($fields['app_name']); ?>" type="text" maxlength="128" placeholder="My Cool App" required>
      </div>

      <div class="field">
        <label class="field-label" for="package_name">Package Name</label>
        <div class="input-with-indicator">
          <input class="input input-mono" id="package_name" name="package_name" value="<?php echo h($fields['package_name']); ?>" type="text" maxlength="191" placeholder="com.example.myapp" required>
          <span class="pkg-indicator" id="pkgIndicator" aria-hidden="true"></span>
        </div>
        <p class="field-hint" id="pkgHint">Unique Android package ID, e.g. com.example.myapp</p>
      </div>

      <div class="field">
        <label class="field-label" for="category_id">Category</label>
        <select class="input" id="category_id" name="category_id" required>
          <option value="">Select a category</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?php echo (int) $c['id']; ?>"<?php echo ((string) $c['id'] === $fields['category_id']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label class="field-label" for="icon_url">Icon URL</label>
        <div class="url-with-preview">
          <input class="input" id="icon_url" name="icon_url" value="<?php echo h($fields['icon_url']); ?>" type="url" maxlength="512" placeholder="https://example.com/icon.png" required>
          <img class="icon-preview" id="iconPreview" alt="Icon preview" hidden>
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="feature_graphic_url">Feature Graphic URL</label>
        <input class="input" id="feature_graphic_url" name="feature_graphic_url" value="<?php echo h($fields['feature_graphic_url']); ?>" type="url" maxlength="512" placeholder="https://example.com/feature.png" required>
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
        <input class="input" id="youtube_url" name="youtube_url" value="<?php echo h($fields['youtube_url']); ?>" type="url" maxlength="255" placeholder="https://www.youtube.com/watch?v=...">
      </div>
    </div>

    <!-- Column 2: binary and description -->
    <div class="upload-col">
      <div class="field">
        <label class="field-label" for="version_code">Version Code</label>
        <input class="input" id="version_code" name="version_code" value="<?php echo h($fields['version_code']); ?>" type="number" min="1" step="1" placeholder="1" required>
        <p class="field-hint">Whole number, increase it with every update.</p>
      </div>

      <div class="field">
        <label class="field-label" for="version_name">Version Name</label>
        <input class="input" id="version_name" name="version_name" value="<?php echo h($fields['version_name']); ?>" type="text" maxlength="32" placeholder="1.0.0" required>
      </div>

      <div class="field">
        <label class="field-label" for="apk_source_url">APK Source Link</label>
        <input class="input" id="apk_source_url" name="apk_source_url" value="<?php echo h($fields['apk_source_url']); ?>" type="url" placeholder="https://github.com/you/repo/releases/download/v1.0.0/app.apk" required>
        <div class="url-with-preview">
          <button class="btn" id="verifyLinkBtn" type="button">Verify Link &amp; Calculate Size</button>
          <input class="input" id="apkSizeDisplay" type="text" placeholder="Size appears here" value="<?php echo h($fields['size_formatted']); ?>" readonly tabindex="-1" aria-label="Detected APK size">
        </div>
        <p class="field-hint" id="apkSizeStatus">Direct .apk link from a GitHub Release or a public Google Drive link.</p>
        <div class="field" id="manualSizeWrap"<?php echo $showManual ? '' : ' hidden'; ?>>
          <label class="field-label" for="manual_size_mb">APK Size (MB)</label>
          <input class="input" id="manual_size_mb" name="manual_size_mb" type="number" min="0.1" max="4096" step="0.1" placeholder="e.g. 25.4" value="<?php echo h($fields['manual_size_mb']); ?>">
          <p class="field-hint">Size detection failed - enter the APK size in megabytes (1 MB = 1024 KB).</p>
        </div>
        <!-- Filled by assets/js/uploader.js; the server re-checks them and saves into app_submissions. -->
        <input type="hidden" id="size_bytes" name="size_bytes" value="<?php echo h($fields['size_bytes']); ?>">
        <input type="hidden" id="size_formatted" name="size_formatted" value="<?php echo h($fields['size_formatted']); ?>">
      </div>

      <div class="banner">
        For APKs larger than 100 MB, please use GitHub Releases for fast, uninterrupted downloads.
      </div>

      <div class="field">
        <label class="field-label" for="description">Description</label>
        <textarea class="input" id="description" name="description" rows="10" placeholder="What does your app do? What is new in this version?" required><?php echo h($fields['description']); ?></textarea>
      </div>
    </div>
  </div>

  <div class="upload-footer">
    <button class="btn btn-primary" id="submitBtn" type="submit">Submit for Review</button>
    <p class="text-tertiary footer-note">Every submission is reviewed before it goes live. Updates keep your current version live until approved.</p>
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
    var oldShots = <?php echo json_encode(array_values($oldShots), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    if (oldShots.length === 0) { oldShots = ['']; }
    oldShots.slice(0, MAX_SHOTS).forEach(function (u) { addShotInput(u); });
    refreshShotStrip();
  })();

  addShotBtn.addEventListener('click', function () {
    if (shotCount() < MAX_SHOTS) { addShotInput(''); }
  });

  refreshShotButtons();
})();
</script>

<script src="<?php echo SITE_URL; ?>/assets/js/uploader.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/common/bottom.php'; ?>
