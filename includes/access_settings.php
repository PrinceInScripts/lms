<?php
/**
 * access_settings.php — GyanSetu LMS
 * 
 * Provides access control functions for users (excluding super_admin).
 * Super_admin always has full access.
 * 
 * Usage:
 *   require_once __DIR__ . '/includes/access_settings.php';
 *   if (hasAccess('students', 'can_view')) { ... }
 *   or shorthand: if (canView('students')) { ... }
 */

if (!defined('GYANSETU_APP')) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

/**
 * Check if a user has a specific permission for a feature.
 * Super_admin automatically returns true.
 *
 * @param string $feature    Feature name (e.g., 'students', 'batches')
 * @param string $permission Permission column: 'can_view', 'can_create', 'can_edit', 'can_delete'
 * @return bool
 */
function hasAccess(string $feature, string $permission = 'can_view'): bool
{
    // Not logged in
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        return false;
    }

    // Super admin has full access
    if ($_SESSION['role'] === 'super_admin') {
        return true;
    }

    // Students have no admin access (they have a separate frontend)
    if ($_SESSION['role'] === 'student') {
        return false;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT $permission FROM user_access_settings WHERE user_id = ? AND feature = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id'], $feature]);
        $result = $stmt->fetchColumn();
        return (bool) $result;
    } catch (Exception $e) {
        error_log('[GyanSetu] hasAccess error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Shorthand: check view permission.
 */
function canView(string $feature): bool
{
    return hasAccess($feature, 'can_view');
}

/**
 * Shorthand: check create permission.
 */
function canCreate(string $feature): bool
{
    return hasAccess($feature, 'can_create');
}

/**
 * Shorthand: check edit permission.
 */
function canEdit(string $feature): bool
{
    return hasAccess($feature, 'can_edit');
}

/**
 * Shorthand: check delete permission.
 */
function canDelete(string $feature): bool
{
    return hasAccess($feature, 'can_delete');
}

/**
 * Get all permissions for a specific user (as an associative array).
 *
 * @param int $user_id
 * @return array<string, array<string, int>> [feature => ['can_view'=>0/1, ...]]
 */
function getUserPermissions(int $user_id): array
{
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT feature, can_view, can_create, can_edit, can_delete FROM user_access_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['feature']] = [
                'can_view'   => (int) $row['can_view'],
                'can_create' => (int) $row['can_create'],
                'can_edit'   => (int) $row['can_edit'],
                'can_delete' => (int) $row['can_delete'],
            ];
        }
        return $permissions;
    } catch (Exception $e) {
        error_log('[GyanSetu] getUserPermissions error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Set (or update) permissions for a user on a specific feature.
 *
 * @param int    $user_id
 * @param string $feature
 * @param int    $can_view   0 or 1
 * @param int    $can_create 0 or 1
 * @param int    $can_edit   0 or 1
 * @param int    $can_delete 0 or 1
 * @param int    $set_by     ID of the admin making this change
 * @return bool
 */
function setUserPermission(int $user_id, string $feature, int $can_view, int $can_create, int $can_edit, int $can_delete, int $set_by): bool
{
    try {
        $db = getDB();
        $db->beginTransaction();

        // Check if record exists
        $stmt = $db->prepare("SELECT id FROM user_access_settings WHERE user_id = ? AND feature = ?");
        $stmt->execute([$user_id, $feature]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $upd = $db->prepare("
                UPDATE user_access_settings 
                SET can_view = ?, can_create = ?, can_edit = ?, can_delete = ?, 
                    set_by = ?, set_at = NOW()
                WHERE user_id = ? AND feature = ?
            ");
            $upd->execute([$can_view, $can_create, $can_edit, $can_delete, $set_by, $user_id, $feature]);
        } else {
            $ins = $db->prepare("
                INSERT INTO user_access_settings (user_id, feature, can_view, can_create, can_edit, can_delete, set_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$user_id, $feature, $can_view, $can_create, $can_edit, $can_delete, $set_by]);
        }

        $db->commit();
        return true;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[GyanSetu] setUserPermission error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Return the list of all features that can be controlled via access settings.
 * Used in the access settings UI.
 *
 * @return array<string, string> [feature_key => Display Name]
 */
function getAvailableFeatures(): array
{
    return [
        'dashboard'   => 'Dashboard',
        'batches'     => 'Batches Management',
        'students'    => 'Student Management',
        'courses'     => 'Courses Management',
        'schedule'    => 'Schedule Management',
        'attendance'  => 'Attendance Management',
        'notes'       => 'Notes Management',
        'assignments' => 'Assignments Management',
        'tests'       => 'Tests Management',
        'exams'       => 'Exams Management',
        'payments'    => 'Payment Management',
        'users'       => 'User Management',
        'settings'    => 'System Settings',
        'notifications' => 'Notifications',
    ];
}