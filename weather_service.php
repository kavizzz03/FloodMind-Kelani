<?php
// weather_service.php

function getStationWeather($station_id) {
    // Map specific coordinate hubs along the Kelani river channel
    $coordinates = [
        1 => ['lat' => 6.9481, 'lon' => 79.8752], // Nagalagam Street (Colombo)
        2 => ['lat' => 6.9122, 'lon' => 80.0811], // Hanwella
        3 => ['lat' => 6.9694, 'lon' => 80.1878], // Glencourse (Avissawella)
        4 => ['lat' => 6.9892, 'lon' => 80.4181], // Kithulgala
        5 => ['lat' => 7.1833, 'lon' => 80.2500], // Holombuwa
        6 => ['lat' => 6.9250, 'lon' => 80.4431], // Deraniyagala
        7 => ['lat' => 6.8412, 'lon' => 80.6133]  // Norwood
    ];

    $loc = $coordinates[$station_id] ?? ['lat' => 6.9271, 'lon' => 79.8612];
    
    $url = "https://api.open-meteo.com/v1/forecast?latitude={$loc['lat']}&longitude={$loc['lon']}&current=temperature_2m,relative_humidity_2m,rain&daily=temperature_2m_max,temperature_2m_min,rain_sum&timezone=Asia%2FColombo&forecast_days=1";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) return null;
    
    $data = json_decode($response, true);
    return [
        'current_temp' => $data['current']['temperature_2m'] ?? 'N/A',
        'humidity'     => $data['current']['relative_humidity_2m'] ?? 'N/A',
        'current_rain' => $data['current']['rain'] ?? 0.0,
        'future_rain'  => $data['daily']['rain_sum'][0] ?? 0.0,
        'max_temp'     => $data['daily']['temperature_2m_max'][0] ?? 'N/A'
    ];
}