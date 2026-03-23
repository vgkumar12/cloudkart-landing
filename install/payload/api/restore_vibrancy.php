<?php
require_once __DIR__ . '/app/Core/Database.php';
use App\Core\Database;

try {
    $db = Database::getConnection();
    
    $crackers_config = [
        'colors' => [
            'primary' => '#0B7C7A', // Deep Teal
            'secondary' => '#EAA42E', // Sun Orange
            'header_bg' => '#FFFFFF', // Clean White
            'footer_bg' => '#0B7C7A', // Matches primary Brand Teal
            'top_bar_bg' => '#0B7C7A', // Matches primary Brand Teal
            'page_bg' => '#f8fafc'
        ],
        'typography' => [
            'primary_font' => 'Poppins',
            'secondary_font' => 'Open Sans'
        ],
        'visuals' => [
            'border_radius' => '8px',
            'shadow' => 'subtle'
        ]
    ];

    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'theme_config_crackers'");
    $stmt->execute([json_encode($crackers_config)]);
    
    // Also update individual colors for compatibility with non-JSON parts if any
    $db->prepare("UPDATE settings SET setting_value = '#0B7C7A' WHERE setting_key = 'primary_color'")->execute();
    $db->prepare("UPDATE settings SET setting_value = '#EAA42E' WHERE setting_key = 'secondary_color'")->execute();
    
    echo "Crackers Theme vibrancy restored with Teal footer and distinct Brand colors.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
