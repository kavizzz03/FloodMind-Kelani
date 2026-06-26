<?php
// insert_reading.php
header('Content-Type: application/json');
date_default_timezone_set('Asia/Colombo');

$host = '127.0.0.1'; $db = 'floodmind kelani'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (\PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database Connection Offline"]);
    exit;
}

$station_id = isset($_POST['station_id']) ? intval($_POST['station_id']) : null;
$raw_sensor_cm = isset($_POST['water_level']) ? floatval($_POST['water_level']) : null;

if ($station_id === null || $raw_sensor_cm === null) {
    echo json_encode(["status" => "bad_request", "message" => "Missing parameters."]);
    exit;
}

// 1. Fetch Station profile rules and target coordinates
$stmt = $pdo->prepare("SELECT * FROM stations WHERE id = ?");
$stmt->execute([$station_id]);
$station = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$station) {
    echo json_encode(["status" => "error", "message" => "Unknown Station Identity"]);
    exit;
}

// 2. Hardware Math Calculations
// Formula: 2 cm tracking directly translates to 1 meter depth scale
$scaled_depth_meters = $raw_sensor_cm / 2.0;
$sriLankaTime = date('Y-m-d H:i:s');

// Hard limit safety check
if ($scaled_depth_meters > 40.0) {
    echo json_encode([
        "status" => "malfunction", 
        "message" => "HARDWARE ERROR: Reading exceeds 40m. Please check physical state of sensor.",
        "scaled_depth_m" => $scaled_depth_meters
    ]);
    exit; // STOP process. DO NOT insert corrupted readings into the database table logs.
}

// 3. Dynamic Threshold Resolution via DB Rules
$alert_status = "Normal";
if ($scaled_depth_meters >= $station['critical_flood_m']) {
    $alert_status = "Critical";
} elseif ($scaled_depth_meters >= $station['major_flood_m']) {
    $alert_status = "Major Flood";
} elseif ($scaled_depth_meters >= $station['minor_flood_m']) {
    $alert_status = "Minor Flood";
}

// 4. Hyper-local environmental API tracking (Open-Meteo Weather Infrastructure)
$temperature = null; $humidity = null; $rainfall = null;
try {
    $lat = $station['latitude']; $lon = $station['longitude'];
    $apiUrl = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current=temperature_2m,relative_humidity_2m,rain&timezone=Asia/Colombo";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4); // Fast fail fallback
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $weatherData = json_decode($response, true);
        if (isset($weatherData['current'])) {
            $temperature = $weatherData['current']['temperature_2m'];
            $humidity    = $weatherData['current']['relative_humidity_2m'];
            $rainfall    = $weatherData['current']['rain'];
        }
    }
} catch (Exception $e) {
    // Graceful error recovery: proceed without weather metadata if the service times out
}

// 5. Commit structured telemetry packet to logs
$insertSql = "INSERT INTO water_level_logs 
    (station_id, raw_sensor_cm, scaled_depth_meters, alert_status, temperature, humidity, rainfall, recorded_at) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($insertSql);
$stmt->execute([
    $station_id, 
    $raw_sensor_cm, 
    $scaled_depth_meters, 
    $alert_status, 
    $temperature, 
    $humidity, 
    $rainfall, 
    $sriLankaTime
]);

echo json_encode([
    "status" => "success",
    "station" => $station['name'],
    "scaled_depth_m" => $scaled_depth_meters,
    "alert" => $alert_status,
    "weather" => ["temp" => $temperature, "hum" => $humidity, "rain" => $rainfall]
]);