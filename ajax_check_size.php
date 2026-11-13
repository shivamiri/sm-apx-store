<?php
/**
 * APK link verification and size auto-detection (Task 4.3).
 *
 * Internal helper endpoint called by assets/js/uploader.js from the
 * developer submission form (upload.php). Accepts a GitHub Release or
 * Google Drive APK link via POST, issues a lightweight HTTP HEAD request
 * (CURLOPT_NOBODY) server-side, and returns the detected file size inside
 * the standard JSON envelope.
 *
 * Access: signed-in users only (same Google session as upload.php).
 * Guests get a JSON 401 instead of a redirect, because this is an AJAX
 * endpoint, not a page.
 *
 * POST body: apk_url=<GitHub Release or Google Drive link>
 *
 * Success: { success: true,  data: { source_type, size_bytes,
 *            size_formatted, manual_required }, error: null }
 * Failure: { success: false, data: { source_type, manual_required: true },
 *            error: "..." } - the form then unlocks the manual size field.
 */

require_once __DIR__ . '/common/config.php';

// ---------- Auth guard (Google session from Task 4.1) ----------

if (empty($_SESSION['user_id'])) {
    jsonResponse(false, null, 'Sign in required', 401);
}

// ---------- Light rate limit: 20 checks per minute per user ----------

if (!checkRateLimit('u' . (int) $_SESSION['user_id'], 'ajax_check_size', 20, 60)) {
    jsonResponse(false, null, 'Too many checks. Wait a minute and try again.', 429);
}

// ---------- Input ----------

$rawUrl = trim((string) ($_POST['apk_url'] ?? ''));

if ($rawUrl === '') {
    jsonResponse(false, null, 'Paste a GitHub Release or Google Drive link first.', 400);
}
if (strlen($rawUrl) > 512) {
    jsonResponse(false, null, 'That link is too long.', 400);
}

/**
 * Classifies an APK link and normalises it to a HEAD-requestable URL.
 *
 * Returns ['type' => 'github'|'gdrive', 'head_url' => string] or null
 * when the link is not a supported source. Only these two hosts are ever
 * contacted, which keeps the endpoint from being used to probe arbitrary
 * internal or external URLs.
 */
function classifyApkUrl(string $url): ?array
{
    if (!preg_match('#^https://#i', $url)) {
        return null;
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return null;
    }
    $host = strtolower($parts['host']);
    $path = $parts['path'] ?? '';

    // GitHub Release asset:
    // https://github.com/<owner>/<repo>/releases/download/<tag>/<file>.apk
    // GitHub answers with a redirect to the asset CDN; curl follows it and
    // the final response carries the real Content-Length.
    if ($host === 'github.com' && strpos($path, '/releases/download/') !== false) {
        return ['type' => 'github', 'head_url' => $url];
    }

    // Google Drive share links, converted to the direct-download form:
    //   https://drive.google.com/file/d/<id>/view?usp=sharing
    //   https://drive.google.com/open?id=<id>
    //   https://drive.google.com/uc?id=<id>&export=download
    if ($host === 'drive.google.com') {
        $fileId = null;
        if (preg_match('#^/file/d/([A-Za-z0-9_-]{10,})#', $path, $m)) {
            $fileId = $m[1];
        } else {
            parse_str($parts['query'] ?? '', $query);
            if (!empty($query['id']) && preg_match('/^[A-Za-z0-9_-]{10,}$/', $query['id'])) {
                $fileId = $query['id'];
            }
        }
        if ($fileId !== null) {
            return [
                'type'     => 'gdrive',
                'head_url' => 'https://drive.google.com/uc?export=download&id=' . $fileId,
            ];
        }
    }

    return null;
}

$link = classifyApkUrl($rawUrl);
if ($link === null) {
    jsonResponse(false, null, 'Only GitHub Release download links and Google Drive share links are supported.', 400);
}

// ---------- HEAD request with curl (no body downloaded) ----------

// Same CA-bundle pattern as common/https.php: this PHP build's default
// certificate path is broken, so verification uses the local bundle.
$caPath = is_dir('/apex/com.android.conscrypt/cacerts')
    ? '/apex/com.android.conscrypt/cacerts'
    : '/system/etc/security/cacerts';

$ch = curl_init($link['head_url']);
curl_setopt_array($ch, [
    CURLOPT_NOBODY         => true,   // HEAD only, never download the APK
    CURLOPT_FOLLOWLOCATION => true,   // GitHub and Drive both redirect
    CURLOPT_MAXREDIRS      => 5,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Linux; Android) SM-Apx-Store-SizeCheck/1.0',
    CURLOPT_CAINFO         => __DIR__ . '/common/cacert-bundle.pem',
    CURLOPT_CAPATH         => $caPath,
]);
curl_exec($ch);
$curlErr  = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$size     = (int) curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
curl_close($ch);

/**
 * Every detection failure takes the same path: report manual_required so
 * the form unlocks the manual size input, with a readable reason.
 */
$fail = static function (string $message) use ($link): void {
    jsonResponse(false, [
        'source_type'     => $link['type'],
        'manual_required' => true,
    ], $message);
};

if ($curlErr !== '') {
    $fail('The link could not be reached. Check it in your browser, then enter the size manually.');
}
if ($httpCode === 0 || $httpCode >= 400) {
    $fail('The file host returned an error (HTTP ' . $httpCode . '). Is the link public? Enter the size manually.');
}
if ($size <= 0) {
    // Drive often omits Content-Length for large files (virus-scan page).
    $fail('The file host did not report a file size. Enter the size manually.');
}

// ---------- Success ----------

$sizeFormatted = number_format($size / 1048576, 1) . ' MB';

// Google Drive throttles downloads over 100 MB; the form keeps a manual
// size confirmation mandatory for those, per the roadmap.
$manualRequired = ($link['type'] === 'gdrive' && $size > 104857600);

jsonResponse(true, [
    'source_type'     => $link['type'],
    'size_bytes'      => $size,
    'size_formatted'  => $sizeFormatted,
    'manual_required' => $manualRequired,
]);
