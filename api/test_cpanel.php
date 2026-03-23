<?php
/**
 * CloudKart CPanel UAPI Test Script
 * This script verifies connectivity and functionality of the CPanelService.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Services/CPanelService.php';

use App\Services\CPanelService;

header('Content-Type: text/plain');

echo "--- CloudKart CPanel UAPI Diagnostics ---\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check Configuration
echo "[1] Checking Configuration...\n";
$config_ok = true;
$required_consts = ['CPANEL_HOST', 'CPANEL_USER', 'CPANEL_TOKEN', 'MAIN_DOMAIN'];

foreach ($required_consts as $const) {
    if (!defined($const) || empty(constant($const))) {
        echo "[-] ERROR: {$const} is not defined or empty in config.php\n";
        $config_ok = false;
    } else {
        $val = constant($const);
        if ($const === 'CPANEL_TOKEN') $val = substr($val, 0, 5) . '...';
        echo "[+] {$const}: {$val}\n";
    }
}

if (!$config_ok) {
    echo "\n[!] Diagnostics aborted: Missing configuration.\n";
    exit;
}

// 2. Initialize Service
echo "\n[2] Initializing CPanelService...\n";
try {
    $cpanel = new CPanelService();
    echo "[+] Service initialized.\n";
} catch (Exception $e) {
    echo "[-] ERROR: Failed to initialize service: " . $e->getMessage() . "\n";
    exit;
}

// 3. Perform Connectivity Test (Mock or Real)
echo "\n[3] Testing Connectivity...\n";
$test_subdomain = 'test-' . time();
$test_db = 'testdb_' . time();

echo "Action: Attempting to create test subdomain '{$test_subdomain}.' on " . MAIN_DOMAIN . "\n";

try {
    // Note: This will actually attempt a real API call if CPANEL_TOKEN is set.
    // If you only want to check connectivity, we might want a simpler method,
    // but CPanelService is currently built to perform specific actions.
    
    // Test Subdomain Creation
    $result = $cpanel->createSubdomain($test_subdomain, MAIN_DOMAIN, '/public_html/' . $test_subdomain);
    
    if ($result['success']) {
        echo "[+] SUCCESS: API call returned success.\n";
        echo "[+] Response Data: " . json_encode($result['data'], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "[-] FAILED: Service returned failure.\n";
    }
    
} catch (Exception $e) {
    echo "[-] EXCEPTION: " . $e->getMessage() . "\n";
    echo "\n--- TROUBLESHOOTING TIPS ---\n";
    echo "1. Verify CPANEL_HOST: Ensure it's the hostname, not a URL (e.g., 'server.example.com').\n";
    echo "2. Verify CPANEL_TOKEN: Ensure it has appropriate permissions (Subdomains, MySQL).\n";
    echo "3. Verify Firewall: Ensure your server can reach ports 2083 (CPanel API).\n";
}

echo "\n--- End of Diagnostics ---\n";
