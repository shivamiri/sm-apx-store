<?php
/** Isolated administrator authentication (Task 5.1). */
require_once __DIR__ . '/../common/config.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if (($_SESSION['admin_logged_in'] ?? false) === true) {
    header('Location: index.php');
    exit;
}

function adminEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}

$error = '';
$username = '';
$pdo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    $username = isset($_POST['username']) && is_string($_POST['username']) ? trim($_POST['username']) : '';
    // Never trim a password: spaces can be part of the chosen password.
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';

    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        http_response_code(403);
        $error = 'Your sign-in form expired. Please submit again.';
    } else {
        // Use the direct client address, never an untrusted forwarded header.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        try {
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new RuntimeException('Client address unavailable.');
            }
            $pdo = getDbConnection();
            $pdo->beginTransaction();

            // Create a zero-hit row if absent. Lock it until this attempt ends,
            // so simultaneous sessions from the same IP cannot bypass the cap.
            $stmt = $pdo->prepare('INSERT INTO rate_limits (ip_address, endpoint, hits, window_start)
                VALUES (?, ?, 0, ?) ON DUPLICATE KEY UPDATE ip_address = VALUES(ip_address)');
            $stmt->execute([$ip, 'admin_login', gmdate('Y-m-d H:i:s')]);
            $stmt = $pdo->prepare('SELECT hits, window_start FROM rate_limits
                WHERE ip_address = ? AND endpoint = ? FOR UPDATE');
            $stmt->execute([$ip, 'admin_login']);
            $rate = $stmt->fetch();
            $retryAfter = max(0, strtotime($rate['window_start'] . ' UTC') + 900 - time());

            if ((int) $rate['hits'] >= 5 && $retryAfter > 0) {
                // Locked requests neither check credentials nor extend the lock.
                $pdo->commit();
                http_response_code(429);
                header('Retry-After: ' . $retryAfter);
                $error = 'Too many failed sign-in attempts. Try again in ' . (int) ceil($retryAfter / 60) . ' minute(s).';
            } else {
                // Reset an expired window explicitly, including its exact expiry
                // second (the shared helper itself uses a strict '<' comparison).
                if ($retryAfter === 0) {
                    $stmt = $pdo->prepare('UPDATE rate_limits SET hits = 0, window_start = ? WHERE ip_address = ? AND endpoint = ?');
                    $stmt->execute([gmdate('Y-m-d H:i:s'), $ip, 'admin_login']);
                }
                $admin = false;
                // Match the admins schema. Bound malformed input before SQL/hash work.
                $validShape = $username !== '' && strlen($username) <= 256
                    && $password !== '' && strlen($password) <= 4096;
                if ($validShape) {
                    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admins WHERE username = ? LIMIT 1');
                    $stmt->execute([$username]);
                    $admin = $stmt->fetch();
                }
                $verified = false;
                if ($validShape) {
                    // Generate a non-credential dummy on every allowed attempt.
                    // Missing usernames still take the password verification path.
                    $dummyHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                    $hash = $admin ? $admin['password_hash'] : $dummyHash;
                    $verified = password_verify($password, $hash);
                }

                if ($admin && $verified) {
                    $stmt = $pdo->prepare('DELETE FROM rate_limits WHERE ip_address = ? AND endpoint = ?');
                    $stmt->execute([$ip, 'admin_login']);
                    $pdo->commit();
                    // Retains the public user's session fields, with a fresh ID.
                    session_regenerate_id(true);
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
                    header('Location: index.php', true, 303);
                    exit;
                }

                // The shared helper increments hits. Only credential failures
                // reach it: GETs, CSRF failures and successful logins do not count.
                $allowed = checkRateLimit($ip, 'admin_login', 5, 900);
                $stmt = $pdo->prepare('SELECT hits FROM rate_limits WHERE ip_address = ? AND endpoint = ?');
                $stmt->execute([$ip, 'admin_login']);
                $failedAttempts = (int) $stmt->fetchColumn();
                if (!$allowed || $failedAttempts >= 5) {
                    // Start the FULL 15-minute lock at failure five, not failure one.
                    $stmt = $pdo->prepare('UPDATE rate_limits SET window_start = ? WHERE ip_address = ? AND endpoint = ?');
                    $stmt->execute([gmdate('Y-m-d H:i:s'), $ip, 'admin_login']);
                    http_response_code(429);
                    header('Retry-After: 900');
                    $error = 'Too many failed sign-in attempts. Try again in 15 minutes.';
                } else {
                    http_response_code(401);
                    $error = 'Invalid username or password.';
                }
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(503);
            $error = 'Sign-in is temporarily unavailable. Please try again later.';
        }
    }
    unset($password, $hash, $dummyHash, $admin);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Sign In | <?php echo adminEscape(APP_NAME); ?></title>
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
    <a class="admin-brand" href="login.php"><span class="admin-mark" aria-hidden="true">A</span><span><?php echo adminEscape(APP_SHORT_NAME); ?> <span class="admin-tag">ADMIN</span></span></a>
    <button class="admin-theme" id="adminTheme" type="button" aria-label="Toggle light or dark theme">Theme</button>
  </header>
  <main class="admin-main">
    <section class="admin-card" aria-labelledby="loginTitle">
      <p class="admin-eyebrow">OWNER ACCESS</p>
      <h1 id="loginTitle">Admin sign in</h1>
      <p class="admin-intro">Manage your store from a separate, protected panel.</p>
      <?php if ($error !== ''): ?>
      <div class="admin-error" role="alert"><?php echo adminEscape($error); ?></div>
      <?php endif; ?>
      <form method="post" action="login.php" class="admin-form">
        <input type="hidden" name="csrf_token" value="<?php echo adminEscape($_SESSION['admin_csrf']); ?>">
        <div class="admin-field">
          <label for="username">Username</label>
          <input class="input" id="username" name="username" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="64" required value="<?php echo adminEscape($username); ?>">
        </div>
        <div class="admin-field">
          <label for="password">Password</label>
          <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn admin-primary" type="submit">Sign in to admin</button>
      </form>
      <p class="admin-note">Admin access is separate from Google sign-in. Five failed attempts lock this IP for 15 minutes.</p>
      <a class="admin-back" href="../index.php">Back to store</a>
    </section>
    <p class="admin-footer"><?php echo adminEscape(APP_NAME); ?> &middot; Administration</p>
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