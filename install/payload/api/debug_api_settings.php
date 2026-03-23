<?php
require_once __DIR__ . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

try {
    $settings = \App\Models\Setting::getPublicSettings();
    echo json_encode($settings, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
