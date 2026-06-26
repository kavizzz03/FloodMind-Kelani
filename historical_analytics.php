<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "127.0.0.1";
$user = "root"; 
$pass = "";     
$db   = "floodmind kelani";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die(json_encode(["error" => "Analytics Engine Connection Failure: " . $e->getMessage()]));
}

// --- AJAX API ROUTE: FETCH HISTORICAL TELEMETRY MATRICES ---
if (isset($_GET['action']) && $_GET['action'] === 'get_historical_data') {
    header('Content-Type: application/json');
    
    $range = $_GET['range'] ?? '24h';
    
    // Core Configuration Matrix: Sets historical boundaries and intelligent downsampling groupings
    switch ($range) {
        case '6h':   
            $interval = "INTERVAL 6 HOUR"; 
            $groupBy = "w.id"; // Raw resolution
            $timeSelect = "w.recorded_at";
            break;
        case '24h':  
            $interval = "INTERVAL 24 HOUR"; 
            $groupBy = "w.id"; // Raw resolution
            $timeSelect = "w.recorded_at";
            break;
        case '2d':   
            $interval = "INTERVAL 2 DAY"; 
            $groupBy = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00:00')"; // Hourly rollups
            $timeSelect = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00') AS recorded_at";
            break;
        case '4d':   
            $interval = "INTERVAL 4 DAY"; 
            $groupBy = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00:00')";
            $timeSelect = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00') AS recorded_at";
            break;
        case '7d':   
            $interval = "INTERVAL 7 DAY"; 
            $groupBy = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00:00')";
            $timeSelect = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00') AS recorded_at";
            break;
        case '2w':   
            $interval = "INTERVAL 14 DAY"; 
            $groupBy = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00:00')";
            $timeSelect = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00') AS recorded_at";
            break;
        case '1m':   
            $interval = "INTERVAL 1 MONTH"; 
            $groupBy = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00:00')"; 
            $timeSelect = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d %H:00') AS recorded_at";
            break;
        case '6m':   
            $interval = "INTERVAL 6 MONTH"; 
            $groupBy = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d 00:00:00')"; // Daily trends
            $timeSelect = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d') AS recorded_at";
            break;
        case '1y':   
            $interval = "INTERVAL 1 YEAR"; 
            $groupBy = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d 00:00:00')";
            $timeSelect = "DATE_FORMAT(w.recorded_at, '%Y-%m-%d') AS recorded_at";
            break;
        default:     
            $interval = "INTERVAL 24 HOUR"; 
            $groupBy = "w.id";
            $timeSelect = "w.recorded_at";
            break;
    }

    $stations = $pdo->query("SELECT id, name, minor_flood_m, major_flood_m, critical_flood_m FROM stations ORDER BY name ASC")->fetchAll();
    $output = [];

    foreach ($stations as $station) {
        // High-speed analytical tracking aggregation query
        $logQuery = "
            SELECT 
                $timeSelect,
                ROUND(AVG(w.scaled_depth_meters), 2) as scaled_depth_meters,
                ROUND(SUM(w.rainfall), 2) as rainfall,
                ROUND(AVG(w.humidity), 0) as humidity,
                ROUND(AVG(w.temperature), 1) as temperature
            FROM water_level_logs w
            WHERE w.station_id = :station_id AND w.recorded_at >= NOW() - $interval
            GROUP BY $groupBy
            ORDER BY w.recorded_at ASC";
        
        $stmt = $pdo->prepare($logQuery);
        $stmt->execute(['station_id' => $station['id']]);
        $logs = $stmt->fetchAll();

        // Safe addition: Only pass records back if data actually exists for this timeframe window
        $output[] = [
            'station_metadata' => $station,
            'timeline_logs' => $logs // Will pass up an empty array if records don't exist
        ];
    }

    echo json_encode($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FloodMind Kelani | Large Scale Hydro-Analytics</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-card {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between font-sans selection:bg-blue-500 selection:text-white">

    <header class="bg-slate-900/90 border-b border-slate-800 sticky top-0 z-50 backdrop-blur-md shadow-xl">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="bg-blue-500/10 p-2 rounded-lg border border-blue-500/20 text-blue-400 text-xl">📈</div>
                <div>
                    <h1 class="text-md font-bold tracking-wider text-slate-100 uppercase">Historical Trend Analyzer</h1>
                    <p class="text-[10px] font-mono text-slate-400 uppercase tracking-widest">FloodMind Climate Core Subsystem</p>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-1.5 bg-slate-950 p-1.5 rounded-xl border border-slate-800">
                <button onclick="changeTimeframe('6h', this)" class="time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter text-slate-400 hover:text-white transition-all cursor-pointer">6H</button>
                <button onclick="changeTimeframe('24h', this)" class="time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter bg-blue-600 text-white transition-all cursor-pointer">24H</button>
                <button onclick="changeTimeframe('2d', this)" class="time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter text-slate-400 hover:text-white transition-all cursor-pointer">2D</button>
                <button onclick="changeTimeframe('4d', this)" class="time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter text-slate-400 hover:text-white transition-all cursor-pointer">4D</button>
                <button onclick="changeTimeframe('7d', this)" class="time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter text-slate-400 hover:text-white transition-all cursor-pointer">7D</button>
                <button onclick="changeTimeframe('2w', this)" class="time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter text-slate-400 hover:text-white transition-all cursor-pointer">2W</button>
                <button onclick="changeTimeframe('1m', this)" class="time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter text-slate-400 hover:text-white transition-all cursor-pointer">1M</button>
                <button onclick="changeTimeframe('6m', this)" class="time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter text-slate-400 hover:text-white transition-all cursor-pointer">6M</button>
                <button onclick="changeTimeframe('1y', this)" class="time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter text-slate-400 hover:text-white transition-all cursor-pointer">1Y</button>
            </div>
        </div>
    </header>

    <main class="max-w-7xl w-full mx-auto p-6 space-y-8 flex-grow">
        
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-2 border-b border-slate-900 pb-4">
            <div>
                <h2 class="text-lg font-black tracking-wide uppercase text-transparent bg-clip-text bg-gradient-to-r from-slate-100 to-slate-400">Station-by-Station Analytics Matrix</h2>
                <p class="text-xs text-slate-400 mt-0.5">Correlating optimized downsampled data matrices to avoid front-end browser overhead.</p>
            </div>
            <a href="dashboard.php" class="text-[11px] font-mono font-bold tracking-wider uppercase border border-slate-800 bg-slate-900 text-slate-300 px-4 py-2 rounded-xl hover:bg-slate-800 transition-all">
                ◀ Return to Control Center
            </a>
        </div>

        <div id="chartsContainerGrid" class="grid grid-cols-1 gap-8">
            <div class="text-center py-20 text-slate-500 font-mono text-xs animate-pulse">
                🔄 Processing historical tracking queries...
            </div>
        </div>
    </main>

    <footer class="bg-slate-950 border-t border-slate-900 py-4">
        <div class="max-w-7xl mx-auto px-6 flex justify-between items-center text-[10px] font-mono text-slate-600">
            <p>&copy; <?= date('Y') ?> FloodMind Data System Engine Module.</p>
            <p class="text-slate-500 uppercase">Status: Secure</p>
        </div>
    </footer>

    <script>
        let currentActiveRange = '24h';
        let instancesMap = {};

        document.addEventListener('DOMContentLoaded', () => {
            loadHistoricalDatasets(currentActiveRange);
        });

        function changeTimeframe(selectedRange, targetButtonElement) {
            document.querySelectorAll('.time-btn').forEach(btn => {
                btn.className = "time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter text-slate-400 hover:text-white transition-all cursor-pointer";
            });
            targetButtonElement.className = "time-btn px-3 py-1.5 rounded-lg text-xs font-mono tracking-tighter bg-blue-600 text-white transition-all cursor-pointer";
            
            currentActiveRange = selectedRange;
            loadHistoricalDatasets(selectedRange);
        }

        function loadHistoricalDatasets(timeframeString) {
            const container = document.getElementById('chartsContainerGrid');
            
            fetch(`?action=get_historical_data&range=${timeframeString}`)
                .then(res => res.json())
                .then(dataArray => {
                    container.innerHTML = '';
                    
                    dataArray.forEach(dataset => {
                        const meta = dataset.station_metadata;
                        const logs = dataset.timeline_logs;
                        
                        const cardId = `station_card_${meta.id}`;
                        const canvasId = `chart_canvas_${meta.id}`;

                        // Structural verification guard: If there are zero telemetry logs, show information statement box
                        if (!logs || logs.length === 0) {
                            container.innerHTML += `
                                <div id="${cardId}" class="glass-card p-8 rounded-2xl border border-slate-900 flex flex-col items-center justify-center text-center space-y-2 min-h-[220px]">
                                    <div class="text-2xl">📡</div>
                                    <h3 class="text-sm font-bold text-slate-300">${meta.name} Station Logs</h3>
                                    <p class="text-xs text-slate-500 font-mono">No telemetry telemetry data records located within the selected window range (${timeframeString.toUpperCase()}).</p>
                                </div>
                            `;
                            return; 
                        }

                        // Map array metrics
                        const labels = logs.map(l => l.recorded_at);
                        const waterLevels = logs.map(l => l.scaled_depth_meters !== null ? parseFloat(l.scaled_depth_meters) : 0);
                        const rainfallValues = logs.map(l => l.rainfall !== null ? parseFloat(l.rainfall) : 0);
                        const humidityValues = logs.map(l => l.humidity !== null ? parseFloat(l.humidity) : 0);

                        // Threshold risk calculations
                        let totalPoints = waterLevels.length;
                        let criticalCount = 0, majorCount = 0, minorCount = 0;

                        waterLevels.forEach(val => {
                            if (val >= parseFloat(meta.critical_flood_m)) criticalCount++;
                            else if (val >= parseFloat(meta.major_flood_m)) majorCount++;
                            else if (val >= parseFloat(meta.minor_flood_m)) minorCount++;
                        });

                        // Calculate adaptive visibility outline colors
                        let hazardBadgeHTML = `<span class="bg-emerald-950/40 text-emerald-400 border border-emerald-900/50 px-2.5 py-1 rounded-lg">Normal Baseline</span>`;
                        let cardOutlineColor = 'border-slate-800/80';

                        if (criticalCount > (totalPoints * 0.2)) {
                            hazardBadgeHTML = `<span class="bg-rose-950/60 text-rose-400 border border-rose-800 px-2.5 py-1 rounded-lg animate-pulse">⚠️ Prolonged Critical Hazard</span>`;
                            cardOutlineColor = 'border-rose-900/60 shadow-lg shadow-rose-950/20';
                        } else if (majorCount > (totalPoints * 0.2)) {
                            hazardBadgeHTML = `<span class="bg-orange-950/50 text-orange-400 border border-orange-800/60 px-2.5 py-1 rounded-lg">⚠️ High Exposure Risk</span>`;
                            cardOutlineColor = 'border-orange-900/50';
                        } else if (minorCount > (totalPoints * 0.2)) {
                            hazardBadgeHTML = `<span class="bg-amber-950/40 text-amber-400 border border-amber-900/40 px-2.5 py-1 rounded-lg">⚠️ Alert Boundary Warning</span>`;
                            cardOutlineColor = 'border-amber-900/40';
                        }

                        // Append visual container card item
                        container.innerHTML += `
                            <div id="${cardId}" class="glass-card p-6 rounded-2xl border ${cardOutlineColor} transition-all space-y-4">
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-slate-900 pb-3">
                                    <div>
                                        <h3 class="text-base font-black text-slate-100">${meta.name} Station Logs</h3>
                                        <p class="text-[10px] font-mono text-slate-500 uppercase mt-0.5">Threshold Limits: Minor: ${meta.minor_flood_m}m | Major: ${meta.major_flood_m}m | Crit: ${meta.critical_flood_m}m</p>
                                    </div>
                                    <div class="text-[10px] font-mono uppercase tracking-wider font-bold">
                                        ${hazardBadgeHTML}
                                    </div>
                                </div>
                                <div class="relative w-full h-[320px]">
                                    <canvas id="${canvasId}"></canvas>
                                </div>
                            </div>
                        `;

                        // Execute Chart.js setup loop bind sequentially
                        setTimeout(() => {
                            const ctx = document.getElementById(canvasId).getContext('2d');
                            if (instancesMap[canvasId]) { instancesMap[canvasId].destroy(); }

                            instancesMap[canvasId] = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [
                                        {
                                            label: 'Water Depth (m)',
                                            data: waterLevels,
                                            borderColor: '#3b82f6',
                                            backgroundColor: 'rgba(59, 130, 246, 0.04)',
                                            borderWidth: 2.5,
                                            pointRadius: totalPoints > 50 ? 0 : 2,
                                            yAxisID: 'yWater',
                                            tension: 0.15,
                                            fill: true,
                                            order: 1
                                        },
                                        {
                                            label: 'Rainfall Volume (mm)',
                                            data: rainfallValues,
                                            borderColor: '#2dd4bf',
                                            backgroundColor: 'transparent',
                                            borderWidth: 2,
                                            pointRadius: totalPoints > 50 ? 0 : 1.5,
                                            borderDash: [3, 3],
                                            yAxisID: 'yRain',
                                            tension: 0.1,
                                            order: 2
                                        },
                                        {
                                            label: 'Humidity (%)',
                                            data: humidityValues,
                                            borderColor: '#fb923c',
                                            backgroundColor: 'transparent',
                                            borderWidth: 1.2,
                                            pointRadius: 0,
                                            yAxisID: 'yHumidity',
                                            tension: 0.15,
                                            order: 3
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: { mode: 'index', intersect: false },
                                    scales: {
                                        x: {
                                            grid: { color: 'rgba(30, 41, 59, 0.2)' },
                                            ticks: { color: '#64748b', font: { size: 9, family: 'monospace' }, maxTicksLimit: 10 }
                                        },
                                        yWater: {
                                            type: 'linear',
                                            position: 'left',
                                            title: { display: true, text: 'Water Level (m)', color: '#3b82f6', font: { size: 10, weight: 'bold' } },
                                            grid: { color: '#1e293b' },
                                            ticks: { color: '#94a3b8', font: { family: 'monospace' } }
                                        },
                                        yRain: {
                                            type: 'linear',
                                            position: 'right',
                                            title: { display: true, text: 'Rainfall (mm)', color: '#2dd4bf', font: { size: 10, weight: 'bold' } },
                                            grid: { display: false },
                                            ticks: { color: '#94a3b8', font: { family: 'monospace' } }
                                        },
                                        yHumidity: {
                                            type: 'linear',
                                            position: 'right',
                                            min: 0, max: 100, display: false
                                        }
                                    },
                                    plugins: {
                                        legend: { labels: { color: '#cbd5e1', font: { size: 10 } } }
                                    }
                                }
                            });
                        }, 50);
                    });
                });
        }
    </script>
</body>
</html>