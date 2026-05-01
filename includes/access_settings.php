<?php
// Access Settings Management

// Function to check if user has access to a feature
function hasAccess($feature, $permission = 'can_view') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'super_admin') {
        return true; // Super admins have full access
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT $permission FROM user_access_settings WHERE user_id = ? AND feature = ?");
        $stmt->execute([$_SESSION['user_id'], $feature]);
        $result = $stmt->fetch();
        
        return $result && $result[$permission] == 1;
    } catch (Exception $e) {
        return false;
    }
}

// Function to get all user permissions
function getUserPermissions($user_id) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM user_access_settings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $permissions = [];
        while ($row = $stmt->fetch()) {
            $permissions[$row['feature']] = [
                'can_view' => $row['can_view'],
                'can_create' => $row['can_create'],
                'can_edit' => $row['can_edit'],
                'can_delete' => $row['can_delete']
            ];
        }
        return $permissions;
    } catch (Exception $e) {
        return [];
    }
}

// Function to set user permissions
function setUserPermission($user_id, $feature, $can_view, $can_create, $can_edit, $can_delete, $set_by) {
    try {
        $db = getDB();
        
        // Check if exists
        $stmt = $db->prepare("SELECT id FROM user_access_settings WHERE user_id = ? AND feature = ?");
        $stmt->execute([$user_id, $feature]);
        
        if ($stmt->fetch()) {
            $stmt = $db->prepare("UPDATE user_access_settings SET can_view = ?, can_create = ?, can_edit = ?, can_delete = ?, set_by = ?, set_at = NOW() WHERE user_id = ? AND feature = ?");
            $stmt->execute([$can_view, $can_create, $can_edit, $can_delete, $set_by, $user_id, $feature]);
        } else {
            $stmt = $db->prepare("INSERT INTO user_access_settings (user_id, feature, can_view, can_create, can_edit, can_delete, set_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $feature, $can_view, $can_create, $can_edit, $can_delete, $set_by]);
        }
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Available features for access control
function getAvailableFeatures() {
    return [
        'dashboard' => 'Dashboard',
        'batches' => 'Batches Management',
        'courses' => 'Courses Management',
        'schedule' => 'Schedule Management',
        'students' => 'Student Management',
        'notes' => 'Notes Management',
        'assignments' => 'Assignments Management',
        'tests' => 'Tests Management',
        'exams' => 'Exams Management',
        'users' => 'User Management',
        'attendance' => 'Attendance Management',
        'notifications' => 'Notifications',
        'payments' => 'Payment Management',
        'settings' => 'System Settings'
    ];
}
?>