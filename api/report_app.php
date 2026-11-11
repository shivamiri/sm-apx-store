<?php
/**
 * POST /api/report_app.php
 * Body (JSON): { "app_id": int, "reason": string, "details": string (optional) }
 * Saves a community abuse report with status 'pending'.
 * Public endpoint: 10 requests per minute per IP.
 */
require_once __DIR__ . '/../common/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!checkRateLimit($ip, 'report_app', 10, 60)) {
    jsonResponse(false, null, 'You have submitted too many reports recently. Please try again later.', 429);
}

$allowedReasons = [
    'Broken Download Link',
    'Malware / Harmful',
    'False Version Display',
    'Inappropriate Content',
    'Other',
];

$invalidMsg = "Invalid report submission. Provide a valid 'app_id' and 'reason'.";

// Read and decode the JSON body.
$raw  = file_get_contents('php://input');
$body = json_decode((string) $raw, true);
if (!is_array($body)) {
    jsonResponse(false, null, $invalidMsg, 400);
}

// app_id: positive integer (number or numeric string).
$appIdRaw = $body['app_id'] ?? null;
if (is_int($appIdRaw)) {
    $appId = $appIdRaw;
} elseif (is_string($appIdRaw) && ctype_digit($appIdRaw)) {
    $appId = (int) $appIdRaw;
} else {
    $appId = 0;
}
if ($appId < 1) {
    jsonResponse(false, null, $invalidMsg, 400);
}

// reason: must be one of the allowed values.
$reason = isset($body['reason']) && is_string($body['reason']) ? trim($body['reason']) : '';
if (!in_array($reason, $allowedReasons, true)) {
    jsonResponse(false, null, $invalidMsg, 400);
}

// details: optional text, max 1000 characters.
$details = null;
if (isset($body['details'])) {
    if (!is_string($body['details'])) {
        jsonResponse(false, null, $invalidMsg, 400);
    }
    $details = trim($body['details']);
    if (mb_strlen($details) > 1000) {
        $details = mb_substr($details, 0, 1000);
    }
    if ($details === '') {
        $details = null;
    }
}

try {
    $pdo = getDbConnection();

    // The app must exist and not be deleted.
    $stmt = $pdo->prepare('SELECT id FROM apps WHERE id = :id AND deleted_at IS NULL');
    $stmt->execute([':id' => $appId]);
    if (!$stmt->fetchColumn()) {
        jsonResponse(false, null, 'The application you are reporting does not exist.', 404);
    }

    $ins = $pdo->prepare(
        "INSERT INTO reports (app_id, reporter_ip, reason, details, status)
         VALUES (:app_id, :ip, :reason, :details, 'pending')"
    );
    $ins->execute([
        ':app_id'  => $appId,
        ':ip'      => $ip,
        ':reason'  => $reason,
        ':details' => $details,
    ]);

    jsonResponse(true, [
        'report_id' => (int) $pdo->lastInsertId(),
        'app_id'    => $appId,
        'status'    => 'pending',
        'message'   => 'Thank you. Your report has been submitted for administrative investigation.',
    ], null, 201);
} catch (Throwable $e) {
    jsonResponse(false, null, 'Server error. Please try again later.', 500);
}
