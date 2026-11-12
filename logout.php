<?php
/**
 * Logs the user out of Apx (clears only the Apx session, not the Google account).
 */

require_once __DIR__ . '/common/config.php';

$_SESSION = [];

// Remove the session cookie too.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

header('Location: /index.php');
exit;