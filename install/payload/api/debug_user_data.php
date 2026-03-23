<?php
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Model.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Models/Customer.php';

use App\Core\Database;

// Mock environment
define('ENVIRONMENT', 'development');

$conn = Database::getConnection();

echo "--- USERS ---\n";
$stmt = $conn->query("SELECT id, name, email, role, created_at FROM users WHERE email LIKE '%vgkumar%' OR name LIKE '%vgkumar%'");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($users);

echo "\n--- CUSTOMERS ---\n";
$stmt = $conn->query("SELECT id, user_id, name, email, phone, created_at FROM customers WHERE email LIKE '%vgkumar%' OR name LIKE '%vgkumar%'");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($customers);

echo "\n--- CUSTOMER EMAIL with + ---\n";
$stmt = $conn->query("SELECT id, user_id, name, email, phone FROM customers WHERE email LIKE '%+%'");
$plusEmail = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($plusEmail);
