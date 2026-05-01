<?php
// Security Headers Configuration

// Start session with secure settings if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true,
        'gc_maxlifetime' => 3600,
    ]);
}

// Security Headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: https:;");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// Disable PHP exposure
header_remove("X-Powered-By");

// Function to generate CSRF token
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Function to verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to generate remember token
function generateRememberToken($user_id) {
    $token = bin2hex(random_bytes(64));
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET remember_token = ?, remember_token_expiry = ? WHERE id = ?");
    $stmt->execute([$token, $expiry, $user_id]);
    
    setcookie('remember_token', $token, time() + (86400 * 30), "/", "", isset($_SERVER['HTTPS']), true);
    return true;
}

// Function to check remember token
function checkRememberToken() {
    if (isset($_COOKIE['remember_token']) && !isset($_SESSION['user_id'])) {
        $token = $_COOKIE['remember_token'];
        
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, role FROM users WHERE remember_token = ? AND remember_token_expiry > NOW() AND status = 'active'");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
    }
    return false;
}
?>