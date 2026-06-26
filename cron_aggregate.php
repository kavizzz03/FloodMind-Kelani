<?php
// cron_aggregate.php
date_default_timezone_set('Asia/Colombo');

$host = '127.0.0.1'; $db = 'floodmind kelani'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (\PDOException $e) { exit; }

// Aggregate data from the last 48 hours to update summaries and catch any delayed inputs
$stmt = $pdo->prepare("
    INSERT INTO hourly_water_analytics (station_id, avg_water_level, min_water_level, max_water_level, calculation_hour)
    SELECT 
        station_id,
        ROUND(AVG(water_level), 2) as avg_water_level,
        MIN(water_level) as min_water_level,
        MAX(water_level) as max_water_level,
        DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') as calc_hour
    FROM water_level_logs
    WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    GROUP BY station_id, calc_hour
    ON DUPLICATE KEY UPDATE 
        avg_water_level = VALUES(avg_water_level),
        min_water_level = VALUES(min_water_level),
        max_water_level = VALUES(max_water_level)
");
$stmt->execute();