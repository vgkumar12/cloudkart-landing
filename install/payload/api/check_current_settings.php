<?php
require_once __DIR__ . '/app/Core/Database.php';
use App\Core\Database;

try {
    $db = Database::getConnection();
    $keys = ['active_theme', 'theme_config_crackers', 'theme_config_organic', 'theme_config_general', 'primary_color', 'secondary_color'];
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($keys);
    
    $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode($results, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
