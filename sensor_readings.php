<?php
date_default_timezone_set('Asia/Colombo');
$host = '127.0.0.1'; $db = 'floodmind kelani'; $user = 'root'; $pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- MICRO-API HANDLER FOR BACKGROUND SYSTEM POLLS ---
if (isset($_GET['api']) && $_GET['api'] === 'fetch_telemetry') {
    header('Content-Type: application/json');
    
    $query = "SELECT s.*, l.scaled_depth_meters, l.alert_status, l.temperature, l.humidity, l.rainfall, l.recorded_at 
              FROM stations s 
              LEFT JOIN water_level_logs l ON l.id = (
                  SELECT id FROM water_level_logs WHERE station_id = s.id ORDER BY recorded_at DESC LIMIT 1
              )";
    $stations = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    $timestamp = date('H:i:s');
    $cardsHtml = '';
    
    foreach ($stations as $row) {
        $isFaulty = false;
        $displayWaterLevel = "No Data";
        
        if (isset($row['scaled_depth_meters'])) {
            if ($row['scaled_depth_meters'] > 40.0) {
                $isFaulty = true;
                $displayWaterLevel = "HARDWARE FAULT";
            } else {
                $displayWaterLevel = number_format($row['scaled_depth_meters'], 2) . " m";
            }
        }

        $statusColor = "text-emerald-400";
        $cardBorder = "border-slate-700";
        $alertLabel = $row['alert_status'] ?? 'Awaiting Init';

        if ($isFaulty) {
            $statusColor = "text-red-500 font-black animate-pulse";
            $cardBorder = "border-red-600/80 bg-red-950/20";
            $alertLabel = "CHECK SENSOR HARDWARE";
        } else {
            if ($alertLabel === 'Critical') {
                $statusColor = "text-red-500 animate-pulse font-black";
            } elseif ($alertLabel === 'Major Flood') {
                $statusColor = "text-orange-500 font-bold";
            } elseif ($alertLabel === 'Minor Flood') {
                $statusColor = "text-yellow-400 font-semibold";
            } elseif ($alertLabel === 'Normal') {
                $statusColor = "text-emerald-400";
            }
        }
        
        $tempDisplay = isset($row['temperature']) ? htmlspecialchars($row['temperature'])."°C" : "--";
        $humDisplay = isset($row['humidity']) ? htmlspecialchars($row['humidity'])."%" : "--";
        $rainDisplay = isset($row['rainfall']) ? htmlspecialchars($row['rainfall'])."mm" : "--";
        $lastContact = isset($row['recorded_at']) ? date('H:i:s', strtotime($row['recorded_at'])) : 'Never';
        $stationName = htmlspecialchars($row['name']);
        
        $cardsHtml .= '
        <div class="bg-slate-800 border '.$cardBorder.' rounded-xl p-4 flex flex-col justify-between transition duration-200">
            <div>
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-sm font-bold text-slate-200 truncate max-w-[180px]">'.$stationName.'</h3>
                    <span class="text-[10px] bg-slate-900 px-2 py-0.5 rounded text-slate-400">ID: '.$row['id'].'</span>
                </div>
                
                <div class="bg-slate-950 p-3 rounded-lg text-center mb-3">
                    <span class="text-xs text-slate-500 block uppercase font-semibold">Scaled Water Level</span>
                    <span class="text-xl font-extrabold '.($isFaulty ? 'text-red-500 tracking-wide' : 'text-cyan-400').'">
                        '.$displayWaterLevel.'
                    </span>
                </div>

                <div class="grid grid-cols-3 gap-1 bg-slate-900 p-2 rounded text-center text-xs mb-3">
                    <div>
                        <span class="text-[9px] block text-slate-500">Temp</span>
                        <span class="font-mono text-slate-300">'.$tempDisplay.'</span>
                    </div>
                    <div>
                        <span class="text-[9px] block text-slate-500">Humidity</span>
                        <span class="font-mono text-slate-300">'.$humDisplay.'</span>
                    </div>
                    <div>
                        <span class="text-[9px] block text-slate-500">Rainfall</span>
                        <span class="font-mono text-slate-300">'.$rainDisplay.'</span>
                    </div>
                </div>
            </div>

            <div class="border-t border-slate-700/60 pt-2 flex justify-between items-center text-[11px]">
                <div>
                    <span class="text-slate-500 block">Status Flag</span>
                    <span class="'.$statusColor.'">'.htmlspecialchars($alertLabel).'</span>
                </div>
                <div class="text-right">
                    <span class="text-slate-500 block">Last Contact</span>
                    <span class="font-mono text-slate-400">'.$lastContact.'</span>
                </div>
            </div>
        </div>';
    }

    echo json_encode([
        "timestamp" => "Asia/Colombo | " . $timestamp,
        "cards_html" => $cardsHtml
    ]);
    exit;
}

// Initial Ingestion Rendering Block
$query = "SELECT s.*, l.scaled_depth_meters, l.alert_status, l.temperature, l.humidity, l.rainfall, l.recorded_at 
          FROM stations s 
          LEFT JOIN water_level_logs l ON l.id = (
              SELECT id FROM water_level_logs WHERE station_id = s.id ORDER BY recorded_at DESC LIMIT 1
          )";
$stations = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>FloodMind Kelani - Sensor Readings Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex flex-col justify-between p-6">
    <div class="max-w-7xl mx-auto space-y-6 w-full">
        
        <header class="border-b border-slate-800 pb-4 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-cyan-400">FloodMind Kelani Dashboard</h1>
                <p class="text-xs text-slate-400">Unified Telemetry Ingestion Engine & Custom Matrix Scheduler</p>
            </div>
            <span id="liveClockBadge" class="bg-slate-800 px-3 py-1 rounded text-xs font-mono text-emerald-400">Asia/Colombo | <?= date('H:i') ?></span>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 space-y-4">
                <div>
                    <h2 class="text-sm font-bold uppercase tracking-wider mb-3 text-cyan-400">System Directives</h2>
                    <div class="flex flex-wrap gap-2">
                        <button onclick="dispatchAction('poll', 'ALL')" class="bg-cyan-600 hover:bg-cyan-500 text-white px-3 py-2 text-xs font-bold rounded transition">Poll All Stations</button>
                        <button onclick="dispatchAction('toggle_auto', '')" class="bg-slate-700 hover:bg-slate-600 px-3 py-2 text-xs font-bold rounded transition">Toggle Automation</button>
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-400 mb-1.5">Modify Evaluation Intervals</label>
                    <select id="intervalRate" onchange="updateIntervalSettings()" class="bg-slate-950 text-xs border border-slate-700 rounded p-2.5 w-full text-slate-200 font-mono focus:border-cyan-500 focus:outline-none">
                        <option value="60">Every 1 Minute (Testing Mode Override)</option>
                        <option value="300">Every 5 Minutes (Short Evaluation Cycle)</option>
                        <option value="600" selected>Every 10 Minutes (Standard Track)</option>
                        <option value="3600">Every 1 Hour (Standard Macro Cycle)</option>
                        <option value="21600">Every 6 Hours (Consolidated Macro Check)</option>
                        <option value="86400">Every 24 Hours (Diurnal Evaluation Frame)</option>
                    </select>
                </div>
            </div>

            <div class="bg-slate-800 p-5 rounded-xl border border-slate-700 lg:col-span-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-cyan-400 mb-3">🗓️ Custom Target Matrix Scheduler</h2>
                <form id="scheduleForm" class="space-y-4">
                    <input type="hidden" name="action" value="create_schedule">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Select Target Array Stations</label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 bg-slate-950 p-3 rounded-lg border border-slate-800 max-h-[90px] overflow-y-auto">
                            <?php foreach($stations as $s): ?>
                                <label class="flex items-center gap-3 text-xs text-slate-300 hover:text-white cursor-pointer p-0.5">
                                    <input type="checkbox" name="stations[]" value="<?= $s['id'] ?>" class="rounded border-slate-700 bg-slate-900 text-cyan-500 focus:ring-cyan-500 w-3.5 h-3.5">
                                    <span class="truncate"><?= htmlspecialchars($s['name']) ?> <span class="text-slate-500 text-[10px]">(ID: <?= $s['id'] ?>)</span></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Execution Target Time</label>
                            <input type="datetime-local" name="execution_time" required class="w-full bg-slate-950 border border-slate-700 rounded p-2 text-xs text-white font-mono focus:border-cyan-500 focus:outline-none">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-2 px-4 rounded text-xs font-bold transition shadow-md">Commit Matrix Job</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="bg-slate-800 p-4 rounded-xl border border-slate-700">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Live Output Diagnostic Log</h3>
            <div id="terminalLog" class="h-32 bg-slate-950 p-3 rounded font-mono text-xs text-green-400 overflow-y-auto space-y-1">
                <div>[SYSTEM] Unified Matrix Application mounted. Monitoring interfaces...</div>
            </div>
        </div>

        <div>
            <h2 class="text-lg font-bold mb-4 text-slate-300">Active River Monitoring Array Station Readings</h2>
            <div id="sensorCardsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach($stations as $row): ?>
                    <?php 
                        $isFaulty = false;
                        $displayWaterLevel = "No Data";
                        
                        if (isset($row['scaled_depth_meters'])) {
                            if ($row['scaled_depth_meters'] > 40.0) {
                                $isFaulty = true;
                                $displayWaterLevel = "HARDWARE FAULT";
                            } else {
                                $displayWaterLevel = number_format($row['scaled_depth_meters'], 2) . " m";
                            }
                        }

                        $statusColor = "text-emerald-400";
                        $cardBorder = "border-slate-700";
                        $alertLabel = $row['alert_status'] ?? 'Awaiting Init';

                        if ($isFaulty) {
                            $statusColor = "text-red-500 font-black animate-pulse";
                            $cardBorder = "border-red-600/80 bg-red-950/20";
                            $alertLabel = "CHECK SENSOR HARDWARE";
                        } else {
                            if ($alertLabel === 'Critical') {
                                $statusColor = "text-red-500 animate-pulse font-black";
                            } elseif ($alertLabel === 'Major Flood') {
                                $statusColor = "text-orange-500 font-bold";
                            } elseif ($alertLabel === 'Minor Flood') {
                                $statusColor = "text-yellow-400 font-semibold";
                            } elseif ($alertLabel === 'Normal') {
                                $statusColor = "text-emerald-400";
                            }
                        }
                    ?>
                    <div class="bg-slate-800 border <?= $cardBorder ?> rounded-xl p-4 flex flex-col justify-between transition duration-200">
                        <div>
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-sm font-bold text-slate-200 truncate max-w-[180px]"><?= htmlspecialchars($row['name']) ?></h3>
                                <span class="text-[10px] bg-slate-900 px-2 py-0.5 rounded text-slate-400">ID: <?= $row['id'] ?></span>
                            </div>
                            
                            <div class="bg-slate-950 p-3 rounded-lg text-center mb-3">
                                <span class="text-xs text-slate-500 block uppercase font-semibold">Scaled Water Level</span>
                                <span class="text-xl font-extrabold <?= $isFaulty ? 'text-red-500 tracking-wide' : 'text-cyan-400' ?>">
                                    <?= $displayWaterLevel ?>
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-1 bg-slate-900 p-2 rounded text-center text-xs mb-3">
                                <div>
                                    <span class="text-[9px] block text-slate-500">Temp</span>
                                    <span class="font-mono text-slate-300"><?= isset($row['temperature']) ? htmlspecialchars($row['temperature'])."°C" : "--" ?></span>
                                </div>
                                <div>
                                    <span class="text-[9px] block text-slate-500">Humidity</span>
                                    <span class="font-mono text-slate-300"><?= isset($row['humidity']) ? htmlspecialchars($row['humidity'])."%" : "--" ?></span>
                                </div>
                                <div>
                                    <span class="text-[9px] block text-slate-500">Rainfall</span>
                                    <span class="font-mono text-slate-300"><?= isset($row['rainfall']) ? htmlspecialchars($row['rainfall'])."mm" : "--" ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-700/60 pt-2 flex justify-between items-center text-[11px]">
                            <div>
                                <span class="text-slate-500 block">Status Flag</span>
                                <span class="<?= $statusColor ?>"><?= htmlspecialchars($alertLabel) ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-500 block">Last Contact</span>
                                <span class="font-mono text-slate-400"><?= isset($row['recorded_at']) ? date('H:i:s', strtotime($row['recorded_at'])) : 'Never' ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <footer class="w-full mt-12 border-t border-slate-800 pt-4 text-center text-xs text-slate-500 space-y-1">
        <p class="font-semibold tracking-wide text-slate-400">FloodMind Kelani Sensor Readings Page</p>
        <p>&copy; <?= date('Y') ?> All Rights Reserved by <span class="text-cyan-500 font-medium">Vexel IT</span> | Engineered & Maintained by <span class="text-emerald-400 font-mono">Kavizz Developer</span></p>
    </footer>

    <script>
        function printLog(text) {
            const consoleBox = document.getElementById('terminalLog');
            const stamp = new Date().toLocaleTimeString('en-US', { hour12: false });
            consoleBox.innerHTML += `<div class="leading-relaxed"><span class="text-slate-500">[${stamp}]</span> ${text}</div>`;
            consoleBox.scrollTop = consoleBox.scrollHeight;
        }

        // Live runtime loop referencing updated file target
        function executeBackgroundTelemetrySync() {
            fetch('sensor_readings.php?api=fetch_telemetry')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('liveClockBadge').innerText = data.timestamp;
                    document.getElementById('sensorCardsContainer').innerHTML = data.cards_html;
                    printLog('<span class="text-emerald-500/80">[AUTO] 30-Second telemetry metrics synchronized.</span>');
                })
                .catch(() => {
                    printLog('<span class="text-amber-500/80">[WARN] Telemetry polling connection timed out. Retrying next cycle.</span>');
                });
        }

        setInterval(executeBackgroundTelemetrySync, 30000);

        function dispatchAction(action, target) {
            printLog(`Routing action (${action}) to execution server via gateway...`);
            fetch(`gateway_handler.php?action=${action}&target=${target}`)
                .then(r => r.json())
                .then(data => {
                    if(data.status === 'success') {
                        printLog(`<span class="text-cyan-400">Success: ${data.message}</span>`);
                        executeBackgroundTelemetrySync();
                    } else {
                        printLog(`<span class="text-red-400">Error: ${data.message}</span>`);
                    }
                }).catch(() => printLog(`<span class="text-red-500">Gateway Pipeline Interrupted.</span>`));
        }

        function updateIntervalSettings() {
            dispatchAction('set_interval', document.getElementById('intervalRate').value);
        }

        document.getElementById('scheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            printLog("Shipping matrix job payload to system database rules table...");
            
            fetch('gateway_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if(data.status === 'success') {
                    printLog(`<span class="text-emerald-400">Success: ${data.message}</span>`);
                    this.reset();
                } else {
                    printLog(`<span class="text-red-400">Execution Error: ${data.message}</span>`);
                }
            })
            .catch(() => printLog(`<span class="text-red-500">Gateway form submission pipeline failed.</span>`));
        });
    </script>
</body>
</html>