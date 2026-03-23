<?php
require_once __DIR__ . '/app/Core/Database.php';
use App\Core\Database;

$db = Database::getConnection();
$stmt = $db->prepare("SELECT setting_key, is_public FROM settings WHERE setting_key = 'active_theme'");
$stmt->execute();
print_r($stmt->fetch(PDO::FETCH_ASSOC));
