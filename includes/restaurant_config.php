<?php
function getRestaurantSettings() {
    $config_file = __DIR__ . '/../config/restaurant_config.json';
    
    // Default settings
    $default_settings = [
        'restaurant_name' => 'Adabina Restaurant',
        'phone' => '+251 712 272 260',
        'email' => 'info@adabinarestaurant.com',
        'location' => 'Central Ethiopia, Gurage Emdibir - Directly in front of Danial Yegebiya Maekel',
        'opening_time' => '01:00',
        'closing_time' => '18:00',
        'description' => 'Experience authentic Ethiopian cuisine in a warm, welcoming atmosphere. From traditional injera to modern fusion dishes, we bring you the best of Ethiopian flavors.'
    ];
    
    // Load settings from file if it exists
    if (file_exists($config_file)) {
        $json_data = file_get_contents($config_file);
        $loaded_settings = json_decode($json_data, true);
        if ($loaded_settings) {
            return array_merge($default_settings, $loaded_settings);
        }
    }
    
    return $default_settings;
}

function formatOperatingHours($opening_time, $closing_time) {
    $opening = date('g:i A', strtotime($opening_time));
    $closing = date('g:i A', strtotime($closing_time));
    return "Mon-Sun: $opening - $closing";
}
?>