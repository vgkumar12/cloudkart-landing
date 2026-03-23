<?php
require_once __DIR__ . '/app/Core/Database.php';
use App\Core\Database;
use App\Models\Setting;

try {
    // Manually include Model if not autoloaded
    require_once __DIR__ . '/app/Models/Setting.php';
    
    $settings = Setting::getPublicSettings();
    echo "--- Public Settings ---\n";
    echo json_encode($settings, JSON_PRETTY_PRINT);
    echo "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
unlink(__FILE__);
