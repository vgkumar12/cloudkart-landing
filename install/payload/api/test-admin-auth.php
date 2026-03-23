<?php
/**
 * Admin Authentication Diagnostic
 * Access: https://suncrackers.in/api/test-admin-auth.php
 */

// Load the application
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Helpers\SessionHelper;

Autoloader::register();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Start admin session
SessionHelper::startSession('admin');

$diagnostic = [
    'success' => true,
    'message' => 'Admin authentication diagnostic',
    'session_id' => session_id(),
    'session_name' => session_name(),
    'cookies_received' => [],
    'session_data' => [],
    'user_authenticated' => false,
    'user_is_admin' => false,
    'user_details' => null
];

// Check cookies
foreach ($_COOKIE as $name => $value) {
    if (strpos($name, 'sa_') === 0 || strpos($name, 'app_') === 0 || $name === 'PHPSESSID') {
        $diagnostic['cookies_received'][$name] = substr($value, 0, 20) . '...';
    }
}

// Check session data
$diagnostic['session_data'] = [
    'user_id' => $_SESSION['user_id'] ?? null,
    'user_email' => $_SESSION['user_email'] ?? null,
    'user_name' => $_SESSION['user_name'] ?? null,
    'user_role' => $_SESSION['user_role'] ?? null,
    'is_admin' => $_SESSION['is_admin'] ?? null,
];

// Check if authenticated
if (!empty($_SESSION['user_id'])) {
    $diagnostic['user_authenticated'] = true;
    
    // Check if admin
    if (!empty($_SESSION['is_admin']) || (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')) {
        $diagnostic['user_is_admin'] = true;
    }
    
    // Get user details from database
    try {
        $db = \App\Core\Database::getConnection();
        $stmt = $db->prepare("SELECT id, name, email, role, is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $diagnostic['user_details'] = $user;
            $diagnostic['user_is_admin'] = $diagnostic['user_is_admin'] || !empty($user['is_admin']) || $user['role'] === 'admin';
        }
    } catch (\Exception $e) {
        $diagnostic['database_error'] = $e->getMessage();
    }
}

// Final verdict
if (!$diagnostic['user_authenticated']) {
    $diagnostic['verdict'] = '❌ NOT AUTHENTICATED - You need to login';
} elseif (!$diagnostic['user_is_admin']) {
    $diagnostic['verdict'] = '❌ NOT ADMIN - You are logged in but not as admin';
} else {
    $diagnostic['verdict'] = '✅ AUTHENTICATED AS ADMIN - Should work!';
}

echo json_encode($diagnostic, JSON_PRETTY_PRINT);
