<?php
/**
 * security_headers.php — GyanSetu LMS
 * Must be the FIRST include on every page (before any output).
 *
 * Responsibilities:
 *   1. Start the session with hardened cookie settings
 *   2. Send all security-related HTTP headers
 *   3. Provide CSRF token helpers
 *
 * Usage:
 *   define('GYANSETU_APP', true);
 *   require_once __DIR__ . '/security_headers.php';
 */

// Guard: block direct access
if (!defined('GYANSETU_APP')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// ── 1. Secure Session ──────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    );

    session_start([
        'cookie_httponly' => true,
        'cookie_secure'   => $isHttps,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,   // reject unknown session IDs
        'gc_maxlifetime'  => 7200,   // 2 hours server-side GC
        'name'            => 'GYANSETU_SID',
    ]);
}

// ── 2. Security Headers ────────────────────────────────────────────────────

// Prevent clickjacking
header('X-Frame-Options: DENY');

// Stop MIME-type sniffing
header('X-Content-Type-Options: nosniff');

// Legacy XSS filter (still respected by some older browsers)
header('X-XSS-Protection: 1; mode=block');

// Control referrer information leakage
header('Referrer-Policy: strict-origin-when-cross-origin');

// Permissions policy — deny features we don't use
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

// HSTS — only send in production (commented out for localhost dev)
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

// Content Security Policy
// Tailwind CDN + RemixIcons + Google Fonts are the only external sources
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; " .
    "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
    "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; " .
    "img-src 'self' data: https:; " .
    "connect-src 'self'; " .
    "frame-ancestors 'none';"
);

// Strip PHP version from headers
header_remove('X-Powered-By');

// ── 3. CSRF Helpers ────────────────────────────────────────────────────────

/**
 * Get (or create) the CSRF token for the current session.
 */
function generateCSRFToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify a submitted CSRF token using constant-time comparison.
 * Regenerates the token after a successful POST to prevent replay.
 */
function verifyCSRFToken(string $submittedToken): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }

    $valid = hash_equals($_SESSION['csrf_token'], $submittedToken);

    // Rotate token after each successful form submission
    if ($valid) {
        unset($_SESSION['csrf_token']);
    }

    return $valid;
}

/**
 * Output a hidden CSRF input field (convenience wrapper for views).
 */
function csrfField(): string
{
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}
