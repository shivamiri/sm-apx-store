<?php
/**
 * Shared HTTPS helper for server-side calls (Google Sign-In, etc.).
 *
 * This PHP build's default certificate path is broken, so every call uses
 * the local CA bundle (common/cacert-bundle.pem) with full verification ON.
 */

/**
 * Makes an HTTPS request and returns the result.
 *
 * @param string     $url        The https:// URL to call.
 * @param array|null $postFields Key-value pairs for a POST body, or null for GET.
 * @param string[]   $headers    Extra request headers (e.g. Authorization).
 * @return array{ok: bool, status: int, body: string|false, error: string}
 */
function apxHttpRequest(string $url, ?array $postFields = null, array $headers = []): array
{
    // The phone's system CA folder (older Android uses the second path).
    $caPath = is_dir('/apex/com.android.conscrypt/cacerts')
        ? '/apex/com.android.conscrypt/cacerts'
        : '/system/etc/security/cacerts';

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CAINFO         => __DIR__ . '/cacert-bundle.pem',
        CURLOPT_CAPATH         => $caPath,
    ];
    if ($postFields !== null) {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($postFields);
    }
    if ($headers !== []) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($ch, $opts);

    $body   = curl_exec($ch);
    $error  = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'ok'     => $body !== false,
        'status' => $status,
        'body'   => $body,
        'error'  => $error,
    ];
}