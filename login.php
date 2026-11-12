<?php
/**
 * Google Sign-In for Apx (Phase 4.1).
 *
 * This single file handles both steps:
 *   1. No "code" and no "error" parameter: send the user to Google's consent screen.
 *   2. "?code=..." (or "?error=...") present: Google sent the user back.
 *      On success, exchange the code for the user's profile and log them in.
 *
 * Sessions used after login: user_id, user_email, user_name, user_avatar.
 */

require_once __DIR__ . '/common/config.php';
require_once __DIR__ . '/common/https.php';

/**
 * Returns a safe local path to send the user back to after login.
 * Only paths starting with a single "/" are allowed, so a crafted
 * "back" value can never send the user to another website.
 */
function apxSafeBack($raw): string
{
    if (!is_string($raw) || $raw === '') {
        return '/index.php';
    }
    if ($raw[0] !== '/' || strpos($raw, '\\') !== false || strpos($raw, ':') !== false) {
        return '/index.php'; // Blocks "http://", "\evil" and "/\evil" tricks.
    }
    if (strlen($raw) > 1 && $raw[1] === '/') {
        return '/index.php'; // Blocks "//evil.com".
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $raw)) {
        return '/index.php'; // Blocks newlines and other control characters.
    }
    return $raw;
}

/**
 * Shows a plain error page and stops. Messages here are safe to show.
 */
function apxLoginError(string $message, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in</title></head>';
    echo '<body style="font-family:sans-serif;max-width:480px;margin:60px auto;padding:0 16px;">';
    echo '<h2>Sign in problem</h2><p>' . htmlspecialchars($message) . '</p>';
    echo '<p><a href="/login.php">Try again</a> &middot; <a href="/index.php">Back to home</a></p>';
    echo '</body></html>';
    exit;
}

// Read query inputs safely: they must be plain strings, never arrays.
if ((isset($_GET['code']) && !is_string($_GET['code']))
    || (isset($_GET['error']) && !is_string($_GET['error']))
    || (isset($_GET['state']) && !is_string($_GET['state']))
    || (isset($_GET['back']) && !is_string($_GET['back']))) {
    apxLoginError('Invalid sign-in link. Please try again.');
}
$inCode  = (isset($_GET['code']) && is_string($_GET['code'])) ? $_GET['code'] : null;
$inError = (isset($_GET['error']) && is_string($_GET['error'])) ? $_GET['error'] : null;
$inState = (isset($_GET['state']) && is_string($_GET['state'])) ? $_GET['state'] : '';
$inBack  = (isset($_GET['back']) && is_string($_GET['back'])) ? $_GET['back'] : '';

// ---------- Step 1: send the user to Google ----------

if ($inCode === null && $inError === null) {
    if (!empty($_SESSION['user_id'])) {
        header('Location: ' . apxSafeBack($inBack));
        exit;
    }

    $state = bin2hex(random_bytes(16)); // CSRF token, checked on return.
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_back']  = apxSafeBack($inBack);

    $query = http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
    ]);
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    exit;
}

// ---------- Step 2: Google sent the user back ----------

// The CSRF token must match the one we stored before redirecting.
if ($inState === '' || empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $inState)) {
    unset($_SESSION['oauth_state'], $_SESSION['oauth_back']);
    apxLoginError('Your sign-in session expired. Please try again.');
}
$back = apxSafeBack($_SESSION['oauth_back'] ?? '');
unset($_SESSION['oauth_state'], $_SESSION['oauth_back']);

// The user cancelled, or Google refused: show a message, do NOT redirect again.
if ($inError !== null) {
    apxLoginError('Google sign-in was cancelled or did not complete.');
}

// Exchange the authorization code for tokens.
$token = apxHttpRequest('https://oauth2.googleapis.com/token', [
    'code'          => $inCode,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);
$tokenData = $token['ok'] ? json_decode($token['body'], true) : null;
if (!$token['ok'] || $token['status'] !== 200 || empty($tokenData['access_token'])) {
    apxLoginError('Could not complete sign-in with Google (token step failed).', 502);
}

// Fetch the user's profile (Google ID, email, name, avatar).
$profile = apxHttpRequest('https://openidconnect.googleapis.com/v1/userinfo', null, [
    'Authorization: Bearer ' . $tokenData['access_token'],
]);
$userInfo = $profile['ok'] ? json_decode($profile['body'], true) : null;
if (!$profile['ok'] || $profile['status'] !== 200 || empty($userInfo['sub']) || empty($userInfo['email'])) {
    apxLoginError('Could not read your Google profile.', 502);
}

// Only trust the email if Google confirms it is verified.
if (empty($userInfo['email_verified'])) {
    apxLoginError('Your Google account email is not verified, so it cannot be used to sign in.', 403);
}

$googleId = (string) $userInfo['sub'];
$email    = (string) $userInfo['email'];
$name     = substr((string) ($userInfo['name'] ?? $email), 0, 128);
$avatar   = (string) ($userInfo['picture'] ?? '');

// ---------- Create or update the user row ----------

try {
    $pdo = getDbConnection();

    // Find the account: first by Google ID (normal repeat login), then by email.
    $stmt = $pdo->prepare('SELECT id, google_id, is_banned, deleted_at FROM users WHERE google_id = ?');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $stmt = $pdo->prepare('SELECT id, google_id, is_banned, deleted_at FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['google_id']) && $user['google_id'] !== $googleId) {
            // Same email tied to a different Google account: never touch that row.
            apxLoginError('This email is already linked to a different Google account.', 409);
        }
    }

    if ($user) {
        // Banned or deactivated accounts are stopped BEFORE anything is changed.
        if (!empty($user['is_banned'])) {
            apxLoginError('This account is banned from Apx.', 403);
        }
        if ($user['deleted_at'] !== null) {
            // Soft-deleted (deactivated) accounts stay blocked so their reviews
            // and history remain intact, per the database design.
            apxLoginError('This account has been deactivated.', 403);
        }

        if (empty($user['google_id'])) {
            // Link this Google account to the existing email account.
            $upd = $pdo->prepare('UPDATE users SET google_id = ?, display_name = ?, avatar_url = ?, updated_at = NOW() WHERE id = ?');
            $upd->execute([$googleId, $name, $avatar, $user['id']]);
        } else {
            // Repeat login: refresh profile details.
            $upd = $pdo->prepare('UPDATE users SET email = ?, display_name = ?, avatar_url = ?, updated_at = NOW() WHERE id = ?');
            $upd->execute([$email, $name, $avatar, $user['id']]);
        }
        $userId = (int) $user['id'];
    } else {
        // Brand-new user.
        $ins = $pdo->prepare('INSERT INTO users (google_id, email, display_name, avatar_url, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
        $ins->execute([$googleId, $email, $name, $avatar]);
        $userId = (int) $pdo->lastInsertId();
    }
} catch (PDOException $e) {
    apxLoginError('A database problem stopped the sign-in. Please try again later.', 500);
}

// New session ID so an old session can never be reused (session fixation).
session_regenerate_id(true);
$_SESSION['user_id']     = $userId;
$_SESSION['user_email']  = $email;
$_SESSION['user_name']   = $name;
$_SESSION['user_avatar'] = $avatar;

header('Location: ' . $back);
exit;