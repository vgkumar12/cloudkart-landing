<?php
header('Content-Type: text/plain');

echo "PHP Version: " . phpversion() . "\n\n";

$extensions = ['pdo', 'pdo_mysql', 'mysqli', 'openssl', 'mbstring', 'json'];

echo "Checking extensions:\n";
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "[✓] $ext is ENABLED\n";
    } else {
        echo "[✗] $ext is DISABLED\n";
    }
}

if (class_exists('PDO')) {
    echo "\nPDO Class: Available\n";
} else {
    echo "\nPDO Class: NOT FOUND\n";
}

try {
    if (class_exists('PDO')) {
        echo "\nAvailable PDO Drivers:\n";
        print_r(PDO::getAvailableDrivers());
    }
} catch (Exception $e) {
    echo "Error getting drivers: " . $e->getMessage();
}
