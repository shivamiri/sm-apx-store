<?php
/** End only the administrator login. The public user stays signed in. */
require_once __DIR__ . '/../common/config.php';
header('Cache-Control: no-store');

// Logout is a CSRF-protected POST from the dashboard, never a GET link.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . (($_SESSION['admin_logged_in'] ?? false) === true ? 'index.php' : 'login.php'));
    exit;
}
$token = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (empty($_SESSION['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $token)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Your sign-out form expired. Go back, refresh the dashboard and try again.';
    exit;
}

unset(
    $_SESSION['admin_logged_in'],
    $_SESSION['admin_id'],
    $_SESSION['admin_username'],
    $_SESSION['admin_csrf']
);
// No session_destroy(), session_unset() or public-user field removal.
session_regenerate_id(true);
header('Location: login.php', true, 303);
exit;