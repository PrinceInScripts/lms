<?php
/**
 * activity_logger.php — GyanSetu LMS
 * Replaces: includes/acitvity_logger.php  (note: fixes the typo in filename)
 *
 * Provides logActivity() and read helpers.
 * Never throws — failures are written to the PHP error log silently so a
 * logging error never breaks the user-facing flow.
 */

if (!defined('GYANSETU_APP')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// ── SQL: create table if it doesn't exist yet ──────────────────────────────
// Run once; subsequent calls are no-ops (IF NOT EXISTS).
// Keep this here so the logger is self-bootstrapping.

function _ensureActivityLogTable(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    try {
        $db = getDB();
        $db->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
                user_id         INT              NULL,
                username        VARCHAR(100)     NULL,
                role            VARCHAR(50)      NULL,
                action          VARCHAR(100)     NOT NULL,
                module          VARCHAR(100)     NULL,
                description     TEXT             NULL,
                ip_address      VARCHAR(45)      NULL,   -- supports IPv6
                user_agent      VARCHAR(500)     NULL,
                request_method  VARCHAR(10)      NULL,
                request_url     VARCHAR(2000)    NULL,
                old_data        JSON             NULL,
                new_data        JSON             NULL,
                created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_user_id   (user_id),
                INDEX idx_action    (action),
                INDEX idx_module    (module),
                INDEX idx_created   (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $ensured = true;
    } catch (Exception $e) {
        error_log('[GyanSetu] Could not ensure activity_logs table: ' . $e->getMessage());
    }
}

// ── Core Logger ────────────────────────────────────────────────────────────

/**
 * Record a user action in the activity_logs table.
 *
 * @param string      $action      Short verb, e.g. 'LOGIN', 'CREATE_STUDENT'
 * @param string|null $module      Feature area, e.g. 'auth', 'students'
 * @param string|null $description Human-readable detail
 * @param mixed       $oldData     State before the change (array or scalar)
 * @param mixed       $newData     State after the change (array or scalar)
 */
function logActivity(
    string $action,
    ?string $module      = null,
    ?string $description = null,
    mixed  $oldData      = null,
    mixed  $newData      = null
): bool {
    _ensureActivityLogTable();

    try {
        $db = getDB();

        // Session context
        $userId   = $_SESSION['user_id']  ?? null;
        $username = $_SESSION['username'] ?? 'guest';
        $role     = $_SESSION['role']     ?? 'guest';

        // Request context — use the helper from functions.php if loaded
        $ip        = function_exists('getClientIP') ? getClientIP() : _fallbackIP();
        $userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $method    = $_SERVER['REQUEST_METHOD'] ?? null;
        $url       = mb_substr($_SERVER['REQUEST_URI'] ?? '', 0, 2000);

        // Encode old/new data to JSON if needed
        $oldJson = _encodeData($oldData);
        $newJson = _encodeData($newData);

        $stmt = $db->prepare("
            INSERT INTO activity_logs
                (user_id, username, role, action, module, description,
                 ip_address, user_agent, request_method, request_url,
                 old_data, new_data)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId, $username, $role,
            strtoupper($action), $module, $description,
            $ip, $userAgent, $method, $url,
            $oldJson, $newJson,
        ]);

        return true;

    } catch (Exception $e) {
        error_log('[GyanSetu] logActivity failed: ' . $e->getMessage());
        return false;
    }
}

// ── Read Helpers ───────────────────────────────────────────────────────────

/**
 * Fetch the N most recent log entries, newest first.
 *
 * @return array<int, array<string, mixed>>
 */
function getRecentActivities(int $limit = 50): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('[GyanSetu] getRecentActivities failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Fetch recent log entries for a specific user.
 *
 * @return array<int, array<string, mixed>>
 */
function getUserActivities(int $userId, int $limit = 20): array
{
    try {
        $db   = getDB();
        $stmt = $db->prepare(
            'SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('[GyanSetu] getUserActivities failed: ' . $e->getMessage());
        return [];
    }
}

/**
 * Fetch log entries filtered by module and optional date range.
 *
 * @return array<int, array<string, mixed>>
 */
function getModuleActivities(
    string  $module,
    int     $limit    = 50,
    ?string $dateFrom = null,
    ?string $dateTo   = null
): array {
    try {
        $db     = getDB();
        $params = [$module];
        $where  = 'WHERE module = ?';

        if ($dateFrom) {
            $where    .= ' AND created_at >= ?';
            $params[]  = $dateFrom;
        }
        if ($dateTo) {
            $where    .= ' AND created_at <= ?';
            $params[]  = $dateTo;
        }

        $params[] = $limit;
        $stmt     = $db->prepare(
            "SELECT * FROM activity_logs {$where} ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log('[GyanSetu] getModuleActivities failed: ' . $e->getMessage());
        return [];
    }
}

// ── Internal Utilities ─────────────────────────────────────────────────────

function _encodeData(mixed $data): ?string
{
    if ($data === null) {
        return null;
    }
    if (is_string($data)) {
        return $data;               // already a string/JSON
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return ($json !== false) ? $json : null;
}

function _fallbackIP(): string
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}
