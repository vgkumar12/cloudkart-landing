<?php
require_once __DIR__ . '/app/Core/Database.php';
require_once __DIR__ . '/app/Core/Model.php';
require_once __DIR__ . '/app/Models/User.php';
require_once __DIR__ . '/app/Models/Customer.php';

use App\Core\Database;

$conn = Database::getConnection();

// Fix Customer ID 25 (linked to User 2)
$cleanEmail = 'vgkumar12@gmail.com';
echo "Updating Customer 25 to $cleanEmail...\n";

$stmt = $conn->prepare("UPDATE customers SET email = ? WHERE id = 25");
$stmt->execute([$cleanEmail]);

echo "Done.\n";

// Check User 2
$stmt = $conn->query("SELECT * FROM users WHERE id = 2");
print_r($stmt->fetch(PDO::FETCH_ASSOC));

// Check Customer 25 again
$stmt = $conn->query("SELECT * FROM customers WHERE id = 25");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
