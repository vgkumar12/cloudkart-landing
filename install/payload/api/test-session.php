<?php
/**
 * Test Session Script
 * Access this directly to test if sessions are working
 */

require_once __DIR__ . '/config.php';

echo "Content-Type: text/html\n\n";
echo "<h1>Session Test</h1>";

echo "<h2>Session Status</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Name: " . session_name() . "</p>";
echo "<p>Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "</p>";

echo "<h2>Session Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Cookie Parameters</h2>";
$params = session_get_cookie_params();
echo "<pre>";
print_r($params);
echo "</pre>";

echo "<h2>Cookies Sent</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<h2>Test Actions</h2>";
echo "<p><a href='?action=set'>Set Test Data</a></p>";
echo "<p><a href='?action=clear'>Clear Session</a></p>";
echo "<p><a href='?action=destroy'>Destroy Session</a></p>";

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'set':
            $_SESSION['test_data'] = 'Hello from session!';
            $_SESSION['test_time'] = date('Y-m-d H:i:s');
            echo "<p style='color: green;'>✅ Test data set!</p>";
            break;
        case 'clear':
            $_SESSION = [];
            echo "<p style='color: orange;'>⚠️ Session cleared!</p>";
            break;
        case 'destroy':
            session_destroy();
            echo "<p style='color: red;'>❌ Session destroyed!</p>";
            break;
    }
}

echo "<h2>Headers Sent</h2>";
echo "<pre>";
print_r(headers_list());
echo "</pre>";
