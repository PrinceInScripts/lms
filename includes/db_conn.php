<?php
// Database connection file with PDO and security configurations

$host = 'localhost';
$dbname = 'u621399201_guru';
$user = 'u621399201_guru';
$pass = 'u$|R1&Tg';

try {
    // PDO with strict error mode and prepared statements by default
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    
    // Set PDO attributes for security
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Set connection charset explicitly
    $db->exec("SET NAMES utf8mb4");
    $db->exec("SET CHARACTER SET utf8mb4");
    $db->exec("SET time_zone = '+05:30'");
    
} catch(PDOException $e) {
    // Log error without exposing details
    error_log("Database connection failed: " . $e->getMessage());
    die("System is temporarily unavailable. Please try again later.");
}

// Global function to get DB connection
function getDB() {
    global $db;
    return $db;
}
?>