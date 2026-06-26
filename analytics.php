<?php
// analytics.php
date_default_timezone_set('Asia/Colombo');
$host = '127.0.0.1'; $db = 'floodmind kelani'; $user = 'root'; $pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) { 
    die("Analytics database channel connection down."); 
}
$stations = $pdo->query("SELECT * FROM stations ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FloodMind - Water Level Historical Chart Matrix</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Kept simple and clean via standard Chart.js framework core distribution -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-4 md:p-8">
    <div class="max-w-7xl mx-auto">
        
        <header class="mb-8 border-b border-slate-800 pb-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-black text-cyan-400 font-mono">📈 Historical Analytics Vault</h1>
                <p class="text-slate-500 text-xs mt-0.5">High performance query metrics isolating large data loads via cache arrays</p>
            </div>
            <div>
                <a href="index.php" class="px-4 py-2 text-xs font-bold bg-slate-900 border border-slate-800 rounded-xl transition text-slate-300 hover:bg-slate-800">← Back To Main Dashboard</a>
            </div>
        </header>

        <div class="bg-slate-900/70 border border-slate-800/80 p-6 rounded-3xl shadow-xl mb-6">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                <div class="w-full sm:w-1/2">
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Target Telemetry Node Matrix</label>
                    <select id="chartStation" onchange="updateAnalyticsHarness()" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-sm text-slate-300 focus:outline-none focus:border-cyan-500 font-mono">
                        <?php foreach($stations as $st): ?>
                            <option value="<?= $st['id'] ?>" data-alert="<?= $st['alert_level'] ?>" data-minor="<?= $st['minor_level'] ?>" data-major="<?= $st['major_level'] ?>">
                                <?= htmlspecialchars($st['station_name']) ?> (<?= $st['unit'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="bg-slate-950 p-1.5 rounded-xl border border-slate-800/80 flex gap-1">
                    <button onclick="changeRange('24h')" id="btn-24h" class="px-4 py-2 text-xs font-bold rounded-lg transition bg-cyan-600 text-white shadow-sm">24H</button>
                    <button onclick="changeRange('4d')"  id="btn-4d"  class="px-4 py-2 text-xs font-bold rounded-lg transition text-slate-400 hover:text-slate-200">4 Days</button>
                    <button onclick="changeRange('7d')"  id="btn-7d"  class="px-4 py-2 text-xs font-bold rounded-lg transition text-slate-400 hover:text-slate-200">7 Days</button>
                    <button onclick="changeRange('30d')" id="btn-30d" class="px-4 py-2 text-xs font-bold rounded-lg transition text-slate-400 hover:text-slate-200">30 Days</button>
                </div>
            </div>

            <!-- Dynamic Legend Indicators for Threshold references -->
            <div class="flex flex-wrap gap-4 mb-4 font-mono text-[11px] justify-start items-center px-2">
                <div class="flex items-center gap-1.5"><span class="w-3 h-0.5 bg-cyan-500 block"></span><span class="text-slate-400">Live Reading (m)</span></div>
                <div class="flex items-center gap-1.5"><span class="w-3 h-0.5 border-t border-dashed border-cyan-400/60 block"></span><span class="text-cyan-400/80" id="lbl-alert">Alert Level: --</span></div>
                <div class="flex items-center gap-1.5"><span class="w-3 h-0.5 border-t border-dashed border-amber-400/60 block"></span><span class="text-amber-400/80" id="lbl-minor">Minor Level: --</span></div>
                <div class="flex items-center gap-1.5"><span class="w-3 h-0.5 border-t border-dashed border-rose-500/70 block"></span><span class="text-rose-400/80" id="lbl-major">Major Level: --</span></div>
            </div>

            <div class="h-[400px] bg-slate-950 p-4 rounded-2xl border border-slate-900 relative shadow-inner mb-6">
                <canvas id="floodMatrixChart"></canvas>
            </div>
        </div>

        <div class="bg-slate-900/70 border border-slate-800/80 p-6 rounded-3xl shadow-xl">
            <h3 class="text-sm font-bold uppercase tracking-widest text-slate-400 font-mono mb-4">🌦️ Extended Meteorological Basin Forecast (Next 4 Days)</h3>
            <div id="weatherForecastGrid" class="grid grid-cols-2 md:grid-cols-4 gap-4 font-mono text-xs text-slate-400">
                <div class="text-center py-4 animate-pulse">Loading localized precipitation telemetry...</div>
            </div>
        </div>

    </div>

    <script>
        let currentRange = '24h';
        let chartInstance = null;

        const stationCoordinates = {
            1: { lat: 6.9481, lon: 79.8752 }, // Nagalagam Street (Colombo)
            2: { lat: 6.9122, lon: 80.0811 }, // Hanwella
            3: { lat: 6.9694, lon: 80.1878 }, // Glencourse (Avissawella)
            4: { lat: 6.9892, lon: 80.4181 }, // Kithulgala
            5: { lat: 7.1833, lon: 80.2500 }, // Holombuwa
            6: { lat: 6.9250, lon: 80.4431 }, // Deraniyagala
            7: { lat: 6.8412, lon: 80.6133 }  // Norwood
        };

        function changeRange(range) {
            ['24h', '4d', '7d', '30d'].forEach(r => {
                document.getElementById(`btn-${r}`).className = r === range 
                    ? "px-4 py-2 text-xs font-bold rounded-lg transition bg-cyan-600 text-white shadow-sm"
                    : "px-4 py-2 text-xs font-bold rounded-lg transition text-slate-400 hover:text-slate-200";
            });
            currentRange = range;
            refreshChartData();
        }

        function updateAnalyticsHarness() {
            refreshChartData();
            fetchExtendedMeteorologicalForecast();
        }

        function refreshChartData() {
            const selectEl = document.getElementById('chartStation');
            const station = selectEl.value;
            
            // Collect the static option properties injected by PHP database entries
            const selectedOpt = selectEl.options[selectEl.selectedIndex];
            const alertVal = parseFloat(selectedOpt.getAttribute('data-alert')) || 0;
            const minorVal = parseFloat(selectedOpt.getAttribute('data-minor')) || 0;
            const majorVal = parseFloat(selectedOpt.getAttribute('data-major')) || 0;

            // Render sub-label indicators to match the conversion rules
            document.getElementById('lbl-alert').innerText = `Alert Level: ${alertVal.toFixed(2)}m`;
            document.getElementById('lbl-minor').innerText = `Minor Level: ${minorVal.toFixed(2)}m`;
            document.getElementById('lbl-major').innerText = `Major Level: ${majorVal.toFixed(2)}m`;

            fetch(`analytics_data.php?range=${currentRange}&station_id=${station}`)
                .then(r => r.json())
                .then(data => {
                    if (chartInstance) chartInstance.destroy();
                    const ctx = document.getElementById('floodMatrixChart').getContext('2d');
                    
                    // Create arrays of identical static numbers matching the length of telemetry readings
                    const dataLength = data.labels.length;
                    const alertLineData = Array(dataLength).fill(alertVal);
                    const minorLineData = Array(dataLength).fill(minorVal);
                    const majorLineData = Array(dataLength).fill(majorVal);

                    chartInstance = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [
                                {
                                    label: 'Live Height (m)',
                                    data: data.values,
                                    borderColor: '#06b6d4',
                                    backgroundColor: 'rgba(6, 182, 212, 0.02)',
                                    borderWidth: 2.5,
                                    tension: 0.15,
                                    fill: true,
                                    pointRadius: currentRange === '24h' ? 2 : 0,
                                    order: 1
                                },
                                {
                                    label: 'Alert Threshold',
                                    data: alertLineData,
                                    borderColor: 'rgba(34, 211, 238, 0.5)',
                                    borderWidth: 1.5,
                                    borderDash: [5, 5],
                                    pointRadius: 0,
                                    fill: false,
                                    order: 2
                                },
                                {
                                    label: 'Minor Flood Threshold',
                                    data: minorLineData,
                                    borderColor: 'rgba(251, 191, 36, 0.5)',
                                    borderWidth: 1.5,
                                    borderDash: [5, 5],
                                    pointRadius: 0,
                                    fill: false,
                                    order: 2
                                },
                                {
                                    label: 'Major Flood Threshold',
                                    data: majorLineData,
                                    borderColor: 'rgba(244, 63, 94, 0.6)',
                                    borderWidth: 2,
                                    borderDash: [6, 4],
                                    pointRadius: 0,
                                    fill: false,
                                    order: 2
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                x: { grid: { color: '#0f172a' }, ticks: { color: '#475569', font: { size: 10 } } },
                                y: { 
                                    grid: { color: '#0f172a' }, 
                                    ticks: { color: '#475569', font: { size: 10 } },
                                    // Give some padding at the top so the major threshold line isn't cut off
                                    suggestedMax: majorVal * 1.2 
                                }
                            }
                        }
                    });
                })
                .catch(err => console.error("Error fetching line metrics data pipeline:", err));
        }

        function fetchExtendedMeteorologicalForecast() {
            const stationId = document.getElementById('chartStation').value;
            const loc = stationCoordinates[stationId] || { lat: 6.9271, lon: 79.8612 };

            fetch(`https://api.open-meteo.com/v1/forecast?latitude=${loc.lat}&longitude=${loc.lon}&daily=temperature_2m_max,temperature_2m_min,rain_sum,relative_humidity_2m_max&timezone=Asia%2FColombo&forecast_days=4`)
                .then(r => r.json())
                .then(data => {
                    let outHtml = '';
                    for (let i = 0; i < 4; i++) {
                        const dateStr = data.daily.time[i];
                        const maxTemp = data.daily.temperature_2m_max[i];
                        const minTemp = data.daily.temperature_2m_min[i];
                        const rainSum = data.daily.rain_sum[i];
                        const maxHum  = data.daily.relative_humidity_2m_max ? data.daily.relative_humidity_2m_max[i] : 'N/A';

                        const rainAlertBorder = rainSum > 50.0 ? 'border-amber-500/60 bg-amber-950/20' : 'border-slate-800 bg-slate-950/50';

                        outHtml += `
                            <div class="p-4 rounded-xl border ${rainAlertBorder}">
                                <div class="text-slate-400 font-bold border-b border-slate-800 pb-1 mb-2">${dateStr}</div>
                                <div class="text-slate-200">🌡️ Temp: <span class="text-slate-50 font-bold">${minTemp}°C - ${maxTemp}°C</span></div>
                                <div class="text-blue-400 mt-1">🌧️ Rainfall: <span class="font-black">${rainSum} mm</span></div>
                                <div class="text-emerald-500 mt-1">💧 Max Hum: <span>${maxHum}%</span></div>
                            </div>
                        `;
                    }
                    document.getElementById('weatherForecastGrid').innerHTML = outHtml;
                })
                .catch(() => {
                    document.getElementById('weatherForecastGrid').innerHTML = `<div class="text-rose-500 font-bold col-span-4 text-center">Failed to fetch meteorological weather data stream.</div>`;
                });
        }

        document.addEventListener("DOMContentLoaded", () => {
            updateAnalyticsHarness();
        });
    </script>
</body>
</html>