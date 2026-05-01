<?php
// Activity Logger for tracking all user actions

function logActivity($action, $module = null, $description = null, $old_data = null, $new_data = null) {
    try {
        $db = getDB();
        
        $user_id = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'Guest';
        $role = $_SESSION['role'] ?? 'guest';
        
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $request_method = $_SERVER['REQUEST_METHOD'] ?? null;
        $request_url = $_SERVER['REQUEST_URI'] ?? null;
        
        // Convert arrays to JSON for storage
        $old_data_json = $old_data ? (is_array($old_data) ? json_encode($old_data) : $old_data) : null;
        $new_data_json = $new_data ? (is_array($new_data) ? json_encode($new_data) : $new_data) : null;
        
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, username, role, action, module, description, ip_address, user_agent, request_method, request_url, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([$user_id, $username, $role, $action, $module, $description, $ip_address, $user_agent, $request_method, $request_url, $old_data_json, $new_data_json]);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

// Function to get recent activities
function getRecentActivities($limit = 50) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Function to get user-specific activities
function getUserActivities($user_id, $limit = 20) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}
?>