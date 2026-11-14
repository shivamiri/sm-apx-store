<?php
/**
 * Tasks 5.3 + 5.4: admin dashboard with the real pending-submissions
 * queue and its Approve / Reject actions.
 *
 * The gatekeeper, sidebar and metric cards all come from
 * admin/common/header.php; this file answers Approve/Reject POSTs first
 * (before any HTML exists), then renders the queue inside the shared
 * layout. The section keeps id="queue" so the sidebar anchor still works.
 *
 * Approve (Task 5.4):
 *   - 'new' submission    -> its data becomes a Live row in `apps`
 *   - 'update' submission -> the target `apps` row gets the new version
 *                            and media; its Live status never pauses
 *   - screenshots JSON is copied into `app_screenshots` (replacing old)
 *   - the submission is marked 'approved' with reviewer + timestamp
 * Reject: mandatory admin note; the submission is marked 'rejected' and
 * the developer reads the note in my-apps.php. A live app is untouched.
 */
require_once __DIR__ . '/../common/config.php';

// Same gatekeeper as admin/common/header.php: POSTs are answered before
// header.php runs, so the session check has to happen here too.
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

/** Published screenshot URLs stay https, host present, bounded length. */
function adminValidShot(string $url): bool
{
    if ($url === '' || strlen($url) > 512 || preg_match('/\s/', $url)) {
        return false;
    }
    $parts = parse_url($url);
    return $parts !== false
        && strtolower($parts['scheme'] ?? '') === 'https'
        && !empty($parts['host']);
}

// ---------- POST: Approve / Reject (Task 5.4) ----------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $token)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Your admin form expired. Go back, refresh the dashboard and try again.';
        exit;
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $submissionId = isset($_POST['submission_id']) && is_string($_POST['submission_id'])
        && ctype_digit($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
    $reason = isset($_POST['reason']) && is_string($_POST['reason']) ? trim($_POST['reason']) : '';

    $flash = null;
    if (!in_array($action, ['approve', 'reject'], true) || $submissionId < 1) {
        $flash = ['error', 'Unknown action. Nothing was changed.'];
    } elseif ($action === 'reject' && (strlen($reason) < 2 || strlen($reason) > 2000)) {
        $flash = ['error', 'Rejection needs a note for the developer (2 to 2000 characters).'];
    } else {
        try {
            $pdo = getDbConnection();
            $pdo->beginTransaction();

            // Lock the submission row until this review ends, so a double
            // tap or second tab can never approve or reject it twice.
            $stmt = $pdo->prepare('SELECT * FROM app_submissions WHERE id = ? FOR UPDATE');
            $stmt->execute([$submissionId]);
            $sub = $stmt->fetch();

            if (!$sub) {
                $pdo->rollBack();
                $flash = ['error', 'Submission not found. It may have been removed.'];
            } elseif (!in_array($sub['status'], ['pending', 'in_review'], true)) {
                $pdo->rollBack();
                $flash = ['error', 'Submission #' . (int) $sub['id'] . ' is already ' . $sub['status'] . '. Nothing was changed.'];
            } elseif ($action === 'reject') {
                // The live app (for an update) is never touched on rejection.
                $stmt = $pdo->prepare("UPDATE app_submissions
                    SET status = 'rejected', admin_notes = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?");
                $stmt->execute([$reason, (int) $_SESSION['admin_id'], $submissionId]);
                $pdo->commit();
                $flash = ['success', 'Rejected "' . $sub['app_name'] . '". The developer sees your note in My Apps.'];
            } else {
                // ----- Approve -----
                $appId = null;
                $error = null;

                if ($sub['submission_type'] === 'update') {
                    $stmt = $pdo->prepare('SELECT id, user_id, deleted_at FROM apps WHERE id = ? FOR UPDATE');
                    $stmt->execute([(int) $sub['app_id']]);
                    $app = $stmt->fetch();
                    if (!$app || $app['deleted_at'] !== null) {
                        $error = 'The live app for this update no longer exists. Reject the submission instead.';
                    } elseif ((int) $app['user_id'] !== (int) $sub['user_id']) {
                        $error = 'The live app belongs to a different developer. Reject the submission instead.';
                    } else {
                        // Zero downtime: the row keeps serving users; only the
                        // version and media move forward. Status stays as-is.
                        $stmt = $pdo->prepare('UPDATE apps SET
                            app_name = ?, category_id = ?, version_code = ?, version_name = ?,
                            apk_source_type = ?, apk_source_url = ?, size_bytes = ?, size_formatted = ?,
                            icon_url = ?, feature_graphic_url = ?, youtube_url = ?, description = ?
                            WHERE id = ?');
                        $stmt->execute([
                            $sub['app_name'], (int) $sub['category_id'],
                            (int) $sub['version_code'], $sub['version_name'],
                            $sub['apk_source_type'], $sub['apk_source_url'],
                            (int) $sub['size_bytes'], $sub['size_formatted'],
                            $sub['icon_url'], $sub['feature_graphic_url'],
                            $sub['youtube_url'] !== null && $sub['youtube_url'] !== '' ? $sub['youtube_url'] : null,
                            $sub['description'], (int) $app['id'],
                        ]);
                        $appId = (int) $app['id'];
                    }
                } else {
                    // New app: the unique key on package_name is the final guard.
                    $stmt = $pdo->prepare('SELECT id FROM apps WHERE package_name = ?');
                    $stmt->execute([$sub['package_name']]);
                    if ($stmt->fetch()) {
                        $error = 'An app with this package name already exists. Reject the submission instead.';
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO apps
                            (user_id, category_id, app_name, package_name, version_code, version_name,
                             apk_source_type, apk_source_url, size_bytes, size_formatted,
                             icon_url, feature_graphic_url, youtube_url, description, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Live')");
                        $stmt->execute([
                            (int) $sub['user_id'], (int) $sub['category_id'],
                            $sub['app_name'], $sub['package_name'],
                            (int) $sub['version_code'], $sub['version_name'],
                            $sub['apk_source_type'], $sub['apk_source_url'],
                            (int) $sub['size_bytes'], $sub['size_formatted'],
                            $sub['icon_url'], $sub['feature_graphic_url'],
                            $sub['youtube_url'] !== null && $sub['youtube_url'] !== '' ? $sub['youtube_url'] : null,
                            $sub['description'],
                        ]);
                        $appId = (int) $pdo->lastInsertId();
                    }
                }

                if ($error !== null) {
                    $pdo->rollBack();
                    $flash = ['error', $error];
                } else {
                    // Copy the submission's screenshot URLs onto the app,
                    // replacing whatever the previous version had.
                    $shots = [];
                    $rawShots = json_decode((string) ($sub['screenshots'] ?? ''), true);
                    if (is_array($rawShots)) {
                        foreach ($rawShots as $shot) {
                            if (is_string($shot) && adminValidShot(trim($shot)) && count($shots) < 8) {
                                $shots[] = trim($shot);
                            }
                        }
                    }
                    $stmt = $pdo->prepare('DELETE FROM app_screenshots WHERE app_id = ?');
                    $stmt->execute([$appId]);
                    $stmt = $pdo->prepare('INSERT INTO app_screenshots (app_id, screenshot_url, sort_order) VALUES (?, ?, ?)');
                    foreach ($shots as $i => $shot) {
                        $stmt->execute([$appId, $shot, $i]);
                    }

                    $stmt = $pdo->prepare("UPDATE app_submissions
                        SET status = 'approved', admin_notes = NULL, reviewed_by = ?, reviewed_at = NOW(), app_id = ?
                        WHERE id = ?");
                    $stmt->execute([(int) $_SESSION['admin_id'], $appId, $submissionId]);
                    $pdo->commit();
                    $flash = ['success', '"' . $sub['app_name'] . '" is now Live on the store.'];
                }
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('admin queue action failed: ' . $e->getMessage());
            $flash = ['error', 'Database error - nothing was changed. Please try again.'];
        }
    }

    // Post/Redirect/Get: a page refresh can never repeat the action.
    $_SESSION['admin_flash'] = $flash;
    header('Location: index.php#queue', true, 303);
    exit;
}

// ---------- GET: flash + queue data ----------

$flash = null;
if (!empty($_SESSION['admin_flash'])) {
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}

$queue = [];
$queueError = false;
try {
    $pdo = getDbConnection();
    $queue = $pdo->query("SELECT s.id, s.submission_type, s.package_name, s.app_name,
            s.version_name, s.version_code, s.size_formatted, s.icon_url,
            s.apk_source_url, s.status, s.created_at,
            u.display_name, u.email, c.name AS category_name
        FROM app_submissions s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN categories c ON c.id = s.category_id
        WHERE s.status IN ('pending', 'in_review')
        ORDER BY s.created_at ASC, s.id ASC")->fetchAll();
} catch (Throwable $e) {
    $queueError = true;
}

/** Icon URLs render only when they are well-formed https links. */
function adminQueueIcon($url): string
{
    $url = trim((string) $url);
    return adminValidShot($url) ? $url : '';
}

function adminQueueInitial($name): string
{
    $name = trim((string) $name);
    return preg_match('/^./us', $name, $match) ? strtoupper($match[0]) : 'A';
}

$admin_page = 'dashboard';
$page_title = 'Dashboard';
require_once __DIR__ . '/common/header.php';
?>
      <section class="admin-card" id="queue" aria-labelledby="queueTitle">
        <p class="admin-eyebrow">SUBMISSIONS QUEUE</p>
        <h1 id="queueTitle">Pending submissions</h1>
        <p class="admin-intro">Review each app, then approve it onto the store or reject it with a note the developer reads in My Apps.</p>

        <?php if ($flash): ?>
        <div class="admin-flash admin-flash-<?php echo $flash[0] === 'success' ? 'success' : 'error'; ?>" role="status"><?php echo adminEscape($flash[1]); ?></div>
        <?php endif; ?>

        <?php if ($queueError): ?>
        <div class="admin-error" role="alert">Could not load the queue. Refresh to try again - nothing was changed.</div>
        <?php elseif (!$queue): ?>
        <div class="admin-placeholder">
          <strong>Queue is clear</strong>
          <p>New developer submissions will appear here for review.</p>
        </div>
        <?php else: ?>
        <ul class="admin-queue" aria-label="Submissions waiting for review">
          <?php foreach ($queue as $item):
              $icon = adminQueueIcon($item['icon_url']);
              $isUpdate = $item['submission_type'] === 'update';
              $submitted = strtotime($item['created_at']);
          ?>
          <li class="admin-queue-item">
            <div class="admin-queue-head">
              <div class="admin-queue-icon" aria-hidden="true">
                <span><?php echo adminEscape(adminQueueInitial($item['app_name'])); ?></span>
                <?php if ($icon !== ''): ?>
                <img src="<?php echo adminEscape($icon); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
                <?php endif; ?>
              </div>
              <div class="admin-queue-title">
                <h2><?php echo adminEscape($item['app_name']); ?></h2>
                <p>v<?php echo adminEscape($item['version_name']); ?> (code <?php echo (int) $item['version_code']; ?>)</p>
              </div>
              <div class="admin-queue-badges">
                <span class="admin-badge <?php echo $isUpdate ? 'admin-badge-update' : 'admin-badge-new'; ?>"><?php echo $isUpdate ? 'Update' : 'New app'; ?></span>
                <?php if ($item['status'] === 'in_review'): ?>
                <span class="admin-badge admin-badge-review">In review</span>
                <?php endif; ?>
              </div>
            </div>
            <dl class="admin-queue-meta">
              <div><dt>Package</dt><dd><?php echo adminEscape($item['package_name']); ?></dd></div>
              <div><dt>Developer</dt><dd><?php echo adminEscape($item['display_name']); ?> <span class="admin-queue-dim">(<?php echo adminEscape($item['email']); ?>)</span></dd></div>
              <div><dt>Category</dt><dd><?php echo adminEscape($item['category_name'] ?? 'Unknown'); ?></dd></div>
              <div><dt>Size</dt><dd><?php echo adminEscape($item['size_formatted']); ?></dd></div>
              <div><dt>Submitted</dt><dd><?php echo adminEscape($submitted ? date('j M Y, g:i A', $submitted) : $item['created_at']); ?></dd></div>
            </dl>
            <div class="admin-queue-actions">
              <a class="btn" href="<?php echo adminEscape($item['apk_source_url']); ?>" target="_blank" rel="noopener noreferrer">Test APK link</a>
              <form method="post" action="index.php#queue" onsubmit="return confirm('Approve and publish this app live on the store?');">
                <input type="hidden" name="csrf_token" value="<?php echo adminEscape($_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="submission_id" value="<?php echo (int) $item['id']; ?>">
                <button class="btn admin-approve" type="submit">Approve</button>
              </form>
            </div>
            <details class="admin-reject">
              <summary>Reject with a note</summary>
              <form method="post" action="index.php#queue">
                <input type="hidden" name="csrf_token" value="<?php echo adminEscape($_SESSION['admin_csrf']); ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="submission_id" value="<?php echo (int) $item['id']; ?>">
                <label for="reason-<?php echo (int) $item['id']; ?>">Note for the developer (required)</label>
                <textarea class="input" id="reason-<?php echo (int) $item['id']; ?>" name="reason" rows="3" maxlength="2000" required placeholder="Example: APK link is broken, please re-upload and submit again."></textarea>
                <button class="btn admin-reject-btn" type="submit">Reject submission</button>
              </form>
            </details>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <a class="admin-back" href="../index.php">Back to store</a>
      </section>
      <script>
        (function () {
          var icons = document.querySelectorAll('.admin-queue-icon img');
          for (var i = 0; i < icons.length; i++) {
            icons[i].addEventListener('error', function () { this.remove(); });
            if (icons[i].complete && icons[i].naturalWidth === 0) {
              icons[i].remove();
            }
          }
        })();
      </script>
<?php require_once __DIR__ . '/common/bottom.php';
