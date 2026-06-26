<?php
// analytics_data.php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$host = '127.0.0.1'; $db = 'floodmind kelani'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    echo json_encode(['labels' => [], 'values' => []]);
    exit;
}

$range = $_GET['range'] ?? '24h';
$station_id = intval($_GET['station_id'] ?? 1);

// Match filter durations to optimal SQL intervals
switch ($range) {
    case '4d':   $interval = '4 DAY';   break;
    case '7d':   $interval = '7 DAY';   break;
    case '30d':  $interval = '30 DAY';  break;
    case '24h':
    default:     $interval = '1 DAY';   break;
}

$stmt = $pdo->prepare("
    SELECT water_level, recorded_at 
    FROM water_level_logs 
    WHERE station_id = :station_id 
      AND recorded_at >= DATE_SUB(NOW(), INTERVAL $interval)
    ORDER BY recorded_at ASC
");
$stmt->execute(['station_id' => $station_id]);
$logs = $stmt->fetchAll();

$labels = [];
$values = [];

foreach ($logs as $log) {
    $val = floatval($log['water_level']);
    
    // Fallback: If old legacy values (> 20 feet) are stored in your log history for Nagalagam,
    // convert them dynamically so your charts do not spike unreasonably.
    if ($station_id === 1 && $val > 20.0) {
        $val = round($val * 0.3048, 2);
    }
    
    // Clean formatting for the time series axis labels
    $timeLabel = ($range === '24h') 
        ? date('H:i', strtotime($log['recorded_at'])) 
        : date('M d H:i', strtotime($log['recorded_at']));

    $labels[] = $timeLabel;
    $values[] = $val;
}

echo json_encode([
    'labels' => $labels,
    'values' => $values
]);