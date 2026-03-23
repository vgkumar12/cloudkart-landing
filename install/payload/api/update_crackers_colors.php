<?php
require_once __DIR__ . '/app/Core/Database.php';
use App\Core\Database;

try {
    $db = Database::getConnection();
    
    $crackers_config = [
        'colors' => [
            'primary' => '#0B7C7A',
            'secondary' => '#EAA42E',
            'header_bg' => '#FFFFFF',
            'footer_bg' => '#1A1A1A',
            'top_bar_bg' => '#0B7C7A',
            'page_bg' => '#f9fafb'
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
    
    echo "Crackers Theme colors updated to match suncrackers.in\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
