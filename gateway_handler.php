<?php
// gateway_handler.php
header('Content-Type: application/json');

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

if ($action === 'create_schedule') {
    $host = '127.0.0.1'; $db = 'floodmind kelani'; $user = 'root'; $pass = '';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (\PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database down"]);
        exit;
    }

    $stations = isset($_POST['stations']) ? $_POST['stations'] : []; // Array of IDs
    $datetime = isset($_POST['execution_time']) ? $_POST['execution_time'] : '';

    if (empty($stations) || empty($datetime)) {
        echo json_encode(["status" => "error", "message" => "Missing Stations or Target Date/Time Allocation"]);
        exit;
    }

    $station_string = implode(',', array_map('intval', $stations));
    $formatted_datetime = date('Y-m-d H:i:s', strtotime($datetime));

    $stmt = $pdo->prepare("INSERT INTO scheduled_jobs (station_ids, target_execution) VALUES (?, ?)");
    $stmt->execute([$station_string, $formatted_datetime]);

    echo json_encode(["status" => "success", "message" => "Targeted schedule registered for: " . $formatted_datetime]);
    exit;
}

// Fallback legacy proxy actions for real-time manual triggers
$target = isset($_GET['target']) ? $_GET['target'] : '';
$command = "";

if ($action === 'poll') {
    $command = ($target === 'ALL') ? "POLL_ALL" : "POLL_STATION:" . intval($target);
} elseif ($action === 'toggle_auto') {
    $command = "TOGGLE_AUTO";
} elseif ($action === 'set_interval') {
    // FIX: Capture the interval seconds passed inside the 'target' URL parameter
    $command = "SET_INTERVAL:" . intval($target);
}

if ($command !== "") {
    $fp = @fsockopen("127.0.0.1", 65432, $errno, $errstr, 1);
    if ($fp) {
        fwrite($fp, $command . "\n");
        $res = stream_get_contents($fp);
        fclose($fp);
        echo json_encode(["status" => "success", "message" => trim($res)]);
    } else {
        echo json_encode(["status" => "error", "message" => "Python service communication failure"]);
    }
    exit;
} else {
    // Catch-all response for unhandled action parameters
    echo json_encode(["status" => "error", "message" => "Invalid or unhandled gateway directive: " . htmlspecialchars($action)]);
    exit;
}