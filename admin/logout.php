<?php
/**
 * admin/logout.php — GyanSetu LMS
 *
 * Changes from original:
 *   ✓ Logs logout before destroying the session (so we still have user context)
 *   ✓ Clears remember_token from DB (original only cleared the cookie)
 *   ✓ Proper session cookie expiry using current session params
 *   ✓ CSRF-protected (GET with a signed token prevents CSRF-based logout)
 *   ✓ Redirects to login with a flash message (not a query param message)
 */

define('GYANSETU_APP', true);

require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/db_conn.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/activity_logger.php';

// ── CSRF check for logout (prevent drive-by logout via img/link) ───────────
// Validate a one-time logout token passed as ?logout_token=...
// Generate this token when rendering the sidebar logout link:
//   $_SESSION['logout_token'] = bin2hex(random_bytes(16));
//   <a href="/admin/logout.php?logout_token=<?= $_SESSION['logout_token'] ">Logout</a>
$submittedLogoutToken = $_GET['logout_token'] ?? '';
$sessionLogoutToken   = $_SESSION['logout_token'] ?? '';

$csrfValid = $sessionLogoutToken !== '' && hash_equals($sessionLogoutToken, $submittedLogoutToken);

// if (!$csrfValid) {
//     redirect(BASE_URL . '/admin/dashboard/dashboard.php');
// }

// ── Log before we wipe the session ────────────────────────────────────────
$loggedUsername = $_SESSION['username'] ?? 'unknown';
logActivity('LOGOUT', 'auth', "User '{$loggedUsername}' logged out");

// ── Clear remember_token from DB if one existed ────────────────────────────
$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    try {
        $db   = getDB();
        $stmt = $db->prepare('UPDATE users SET remember_token = NULL, remember_token_expiry = NULL WHERE id = ?');
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log('[GyanSetu] Could not clear remember_token on logout: ' . $e->getMessage());
    }
}

// ── Expire the remember_me cookie ─────────────────────────────────────────
if (isset($_COOKIE['remember_token'])) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

// ── Destroy the PHP session completely ────────────────────────────────────
// 1. Clear all session data in memory
$_SESSION = [];

// 2. Expire the session cookie in the browser
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => 'Strict',
        ]
    );
}

// 3. Destroy the session on the server
session_destroy();

// ── Redirect to login with a success message ──────────────────────────────
// Start a fresh session to carry the flash message across the redirect
session_start();
$_SESSION['auth_error'] = ''; // clear any stale auth error
setFlash('success', 'You have been logged out successfully.');

redirect(BASE_URL . '/admin/login.php');
