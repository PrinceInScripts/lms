<?php
/**
 * functions.php — GyanSetu LMS
 * Global helper functions shared across the entire application.
 *
 * Sections:
 *   1. Input sanitization
 *   2. Redirection
 *   3. Unique ID generation
 *   4. Flash messages (success / error / warning / info)
 *   5. Miscellaneous utilities
 */

if (!defined('GYANSETU_APP')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

define('BASE_URL', '/Project/lms');
// ── 1. Input Sanitization ──────────────────────────────────────────────────

/**
 * Sanitize a plain-text string for safe use in HTML output.
 * Always use this when echoing user-supplied data.
 */
function sanitize(mixed $value): string
{
    return htmlspecialchars(trim((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize and cast to integer. Returns null if the value is not numeric.
 */
function sanitizeInt(mixed $value): ?int
{
    $v = filter_var($value, FILTER_VALIDATE_INT);
    return ($v !== false) ? (int) $v : null;
}

/**
 * Validate and return a sanitized e-mail address, or null if invalid.
 */
function sanitizeEmail(mixed $value): ?string
{
    $v = filter_var(trim((string) $value), FILTER_VALIDATE_EMAIL);
    return ($v !== false) ? strtolower($v) : null;
}

/**
 * Strip everything except digits, letters, spaces, and common punctuation.
 * Use for names, titles, descriptions.
 */
function sanitizeText(mixed $value, int $maxLen = 255): string
{
    $clean = preg_replace('/[^\p{L}\p{N}\s\-_.,!?@#()\':\/]/u', '', trim((string) $value));
    return mb_substr($clean, 0, $maxLen);
}

// ── 2. Redirection ─────────────────────────────────────────────────────────

/**
 * Redirect to a URL and halt execution.
 * Defaults to a relative URL from the project root.
 */
function redirect(string $url): never
{
    // Prevent header injection
    $url = preg_replace('/[\r\n]/', '', $url);
    header('Location: ' . $url);
    exit();
}

/**
 * Redirect back to the previous page (via HTTP_REFERER), with a fallback.
 */
function redirectBack(string $fallback = '/admin/dashboard/dashboard.php'): never
{
    $referer = $_SERVER['HTTP_REFERER'] ?? $fallback;
    redirect($referer);
}

// ── 3. Unique ID Generation ────────────────────────────────────────────────

/**
 * Generate a student enrollment ID.
 * Format: GS-YYYY-XXXXXX  (GS = GyanSetu, YYYY = year, XXXXXX = random hex)
 *
 * Example: GS-2025-A3F9C1
 */
function generateStudentId(): string
{
    $year   = date('Y');
    $random = strtoupper(bin2hex(random_bytes(3))); // 6 hex chars
    return "GS-{$year}-{$random}";
}

/**
 * Generate a cryptographically random token (URL-safe base64).
 */
function generateToken(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Generate a UUID v4.
 */
function generateUUID(): string
{
    $data    = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // variant RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

// ── 4. Flash Messages ──────────────────────────────────────────────────────

/**
 * Store a flash message in the session (survives one redirect).
 *
 * @param string $type  'success' | 'error' | 'warning' | 'info'
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

/**
 * Retrieve and clear all flash messages of all types.
 *
 * @return array<string, string[]>  e.g. ['success' => [...], 'error' => [...]]
 */
function getFlashMessages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/**
 * Render flash messages as styled Tailwind HTML.
 * Call this once near the top of your page content area.
 */
function renderFlashMessages(): void
{
    $all = getFlashMessages();
    if (empty($all)) {
        return;
    }

    $styles = [
        'success' => ['bg' => 'bg-green-50', 'border' => 'border-green-400', 'text' => 'text-green-800',  'icon' => 'ri-checkbox-circle-line'],
        'error'   => ['bg' => 'bg-red-50',   'border' => 'border-red-400',   'text' => 'text-red-800',    'icon' => 'ri-error-warning-line'],
        'warning' => ['bg' => 'bg-yellow-50','border' => 'border-yellow-400','text' => 'text-yellow-800', 'icon' => 'ri-alert-line'],
        'info'    => ['bg' => 'bg-blue-50',  'border' => 'border-blue-400',  'text' => 'text-blue-800',   'icon' => 'ri-information-line'],
    ];

    echo '<div class="flash-messages space-y-2 mb-4" id="flash-container">';
    foreach ($all as $type => $messages) {
        $s = $styles[$type] ?? $styles['info'];
        foreach ($messages as $msg) {
            $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
            echo <<<HTML
            <div class="flex items-center gap-3 p-4 rounded-xl border-l-4 {$s['bg']} {$s['border']} {$s['text']} text-sm font-medium shadow-sm animate-pulse-once">
                <i class="{$s['icon']} text-lg flex-shrink-0"></i>
                <span>{$safe}</span>
            </div>
            HTML;
        }
    }
    echo '</div>';

    // Auto-dismiss after 4 seconds
    echo <<<JS
    <script>
        setTimeout(() => {
            const c = document.getElementById('flash-container');
            if (c) c.style.transition = 'opacity 0.5s', c.style.opacity = '0',
                setTimeout(() => c.remove(), 500);
        }, 4000);
    </script>
    JS;
}

// ── 5. Miscellaneous Utilities ─────────────────────────────────────────────

/**
 * Format a MySQL datetime string for display in IST.
 * Returns a human-friendly string like "02 May 2025, 10:30 AM".
 */
function formatDate(string $datetime, string $format = 'd M Y, h:i A'): string
{
    try {
        $dt = new DateTimeImmutable($datetime, new DateTimeZone('Asia/Kolkata'));
        return $dt->format($format);
    } catch (Exception) {
        return $datetime; // return raw string if parsing fails
    }
}

/**
 * Truncate a string to $maxLen characters and append "…" if needed.
 */
function truncate(string $text, int $maxLen = 80): string
{
    return mb_strlen($text) > $maxLen
        ? mb_substr($text, 0, $maxLen - 1) . '…'
        : $text;
}

/**
 * Return the client's real IP address, accounting for common proxies.
 * NOTE: X-Forwarded-For can be spoofed — use for logging only, not auth.
 */
function getClientIP(): string
{
    $candidates = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    foreach ($candidates as $key) {
        if (!empty($_SERVER[$key])) {
            // X-Forwarded-For can be a comma-separated list; take the first
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

/**
 * Check whether the current request is a POST.
 */
function isPost(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Safe integer cast from $_GET / $_POST with an optional default.
 */
function getInt(array $source, string $key, int $default = 0): int
{
    return isset($source[$key]) ? (int) filter_var($source[$key], FILTER_SANITIZE_NUMBER_INT) : $default;
}

/**
 * Safe string retrieval from $_GET / $_POST.
 */
function getString(array $source, string $key, string $default = ''): string
{
    return isset($source[$key]) ? sanitize($source[$key]) : $default;
}
