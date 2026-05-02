<?php
/**
 * auth.php — GyanSetu LMS
 * Centralized authentication & authorization guard.
 * Include at the TOP of every protected admin page.
 *
 * Usage:
 *   require_once __DIR__ . '/../includes/auth.php';
 *   requireAdmin();               // super_admin only
 *   requireRole('admin');         // any specific role
 *   requireAnyRole(['super_admin', 'admin']);
 */

// Prevent direct access
if (!defined('GYANSETU_APP')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

/**
 * Ensure a valid, active admin session exists.
 * Regenerates session ID to prevent fixation attacks.
 * Redirects to login if unauthenticated or unauthorized.
 */
function requireAdmin(): void
{
    requireAnyRole(['super_admin']);
}

/**
 * Allow access to a single role only.
 */
function requireRole(string $role): void
{
    requireAnyRole([$role]);
}

/**
 * Allow access to one of multiple roles.
 *
 * @param string[] $allowedRoles
 */
function requireAnyRole(array $allowedRoles): void
{
    // Session must already be started by security_headers.php
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Fallback: start with safe defaults if somehow not started yet
        session_start([
            'cookie_httponly' => true,
            'cookie_secure'   => isset($_SERVER['HTTPS']),
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'gc_maxlifetime'  => 3600,
        ]);
    }

    // Not logged in at all → redirect to login
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        _redirectToLogin('Please log in to continue.');
    }

    // Regenerate session ID on every request to prevent session fixation.
    // We do it at most once per request using a flag stored in the session.
    if (empty($_SESSION['__id_regenerated'])) {
        session_regenerate_id(true);
        $_SESSION['__id_regenerated'] = true;
    }

    // Role check
    if (!in_array($_SESSION['role'], $allowedRoles, true)) {
        // Log the unauthorized attempt
        if (function_exists('logActivity')) {
            logActivity(
                'UNAUTHORIZED_ACCESS',
                'auth',
                'Attempt to access ' . ($_SERVER['REQUEST_URI'] ?? '') .
                ' — required roles: ' . implode(', ', $allowedRoles)
            );
        }
        http_response_code(403);
        _redirectToLogin('You do not have permission to access this page.');
    }

    // Session timeout: 2 hours of inactivity
    $timeout = 7200;
    if (!empty($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        _redirectToLogin('Your session has expired. Please log in again.');
    }

    // Refresh activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * Check whether the current user is logged in (non-redirecting).
 */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Return the logged-in user's role, or null if not logged in.
 */
function currentRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Return the logged-in user's ID, or null if not logged in.
 */
function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

// ── Internal helper ────────────────────────────────────────────────────────

function _redirectToLogin(string $message = ''): never
{
    // Calculate the path to login.php relative to the project root
    $loginPath = '/admin/login.php';

    if ($message) {
        $_SESSION['auth_error'] = $message;
    }

    header('Location: ' . $loginPath);
    exit();
}
