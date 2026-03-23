<?php
/**
 * Test if Authorization header is reaching PHP
 * Access this file directly: https://suncrackers.in/api/test-auth-header.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$headers = [];
$authHeader = null;

// Check all possible ways the Authorization header might come through
$checks = [
    'HTTP_AUTHORIZATION' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null,
    'Authorization from getallheaders' => null,
];

// Try getallheaders if available
if (function_exists('getallheaders')) {
    $allHeaders = getallheaders();
    $checks['Authorization from getallheaders'] = $allHeaders['Authorization'] ?? $allHeaders['authorization'] ?? null;
}

// Try apache_request_headers if available
if (function_exists('apache_request_headers')) {
    $apacheHeaders = apache_request_headers();
    $checks['Authorization from apache_request_headers'] = $apacheHeaders['Authorization'] ?? $apacheHeaders['authorization'] ?? null;
}

// Find the first non-null auth header
foreach ($checks as $source => $value) {
    if ($value !== null) {
        $authHeader = $value;
        break;
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Authorization header test',
    'authorization_header_found' => $authHeader !== null,
    'authorization_header_value' => $authHeader ? substr($authHeader, 0, 50) . '...' : null,
    'all_checks' => $checks,
    'all_server_vars' => array_filter($_SERVER, function($key) {
        return strpos($key, 'AUTH') !== false || strpos($key, 'HTTP_') === 0;
    }, ARRAY_FILTER_USE_KEY)
], JSON_PRETTY_PRINT);
