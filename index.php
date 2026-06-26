<?php
// ==========================================
// LANDING PAGE – FloodMind Kelani (Modern)
// ==========================================
session_start();

$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "floodmind kelani";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    $dbError = true;
}

$currentLevel = null;
$previousLevel = null;
$forecast = null;
$stationName = 'Kelani Ganga';

if (!isset($dbError)) {
    $stationQuery = "SELECT id, name FROM stations LIMIT 1";
    $station = $pdo->query($stationQuery)->fetch();
    if ($station) {
        $stationName = $station['name'];
        // Latest and previous water levels
        $levelQuery = "
            SELECT scaled_depth_meters, recorded_at 
            FROM water_level_logs 
            WHERE station_id = :sid 
            ORDER BY recorded_at DESC LIMIT 2";
        $stmt = $pdo->prepare($levelQuery);
        $stmt->execute(['sid' => $station['id']]);
        $rows = $stmt->fetchAll();
        if (count($rows) >= 1) {
            $currentLevel = $rows[0];
            if (count($rows) >= 2) {
                $previousLevel = $rows[1];
            }
        }

        // Forecast for tomorrow
        $forecastQuery = "
            SELECT expected_rainfall_mm, expected_temp_c, expected_humidity, forecast_date
            FROM weather_forecast_logs
            WHERE station_id = :sid AND forecast_date >= CURDATE()
            ORDER BY forecast_date ASC LIMIT 1";
        $fStmt = $pdo->prepare($forecastQuery);
        $fStmt->execute(['sid' => $station['id']]);
        $forecast = $fStmt->fetch();
    }
}

if (!$currentLevel) {
    $currentLevel = ['scaled_depth_meters' => 1.85, 'recorded_at' => date('Y-m-d H:i:s')];
}
if (!$forecast) {
    $forecast = [
        'expected_rainfall_mm' => 12.5,
        'expected_temp_c' => 28.5,
        'expected_humidity' => 82,
        'forecast_date' => date('Y-m-d', strtotime('+1 day'))
    ];
}

// Calculate trend (up/down/stable)
$trend = 'stable';
$trendIcon = '→';
$trendColor = '#94a3b8';
if ($previousLevel && isset($previousLevel['scaled_depth_meters'])) {
    $diff = $currentLevel['scaled_depth_meters'] - $previousLevel['scaled_depth_meters'];
    if ($diff > 0.01) {
        $trend = 'up';
        $trendIcon = '↑';
        $trendColor = '#ef4444';
    } elseif ($diff < -0.01) {
        $trend = 'down';
        $trendIcon = '↓';
        $trendColor = '#22c55e';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>FloodMind Kelani – Loading</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .hero-bg {
            background: linear-gradient(135deg, rgba(15,23,42,0.85) 0%, rgba(15,23,42,0.92) 100%),
                        url('https://images.unsplash.com/photo-1541701494587-cb58502866ab?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .glass-card {
            background: rgba(30, 41, 59, 0.55);
            backdrop-filter: blur(16px) saturate(180%);
            border: 1px solid rgba(255,255,255,0.06);
            box-shadow: 0 30px 60px -15px rgba(0,0,0,0.7);
            position: relative;
            overflow: hidden;
        }
        /* Animated gradient border */
        .glass-card::before {
            content: '';
            position: absolute;
            inset: -2px;
            border-radius: 1.5rem;
            padding: 2px;
            background: conic-gradient(from 0deg, #3b82f6, #14b8a6, #8b5cf6, #3b82f6);
            background-size: 300% 300%;
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            animation: borderSpin 6s linear infinite;
            pointer-events: none;
        }
        @keyframes borderSpin {
            0% { background-position: 0% 0%; }
            100% { background-position: 300% 300%; }
        }
        .water-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 60px;
            opacity: 0.15;
            pointer-events: none;
        }
        .progress-bar-track {
            background: rgba(51,65,85,0.6);
            height: 4px;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #3b82f6, #14b8a6);
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        .stat-value {
            font-feature-settings: "tnum";
        }
        .gauge-container {
            width: 100%;
            height: 120px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 4px;
        }
        .gauge-bar {
            width: 20px;
            background: #1e293b;
            border-radius: 4px 4px 0 0;
            position: relative;
            transition: height 1s ease;
        }
        .gauge-bar.fill {
            background: linear-gradient(180deg, #3b82f6, #14b8a6);
            border: 1px solid rgba(59,130,246,0.3);
        }
        .gauge-label {
            font-size: 10px;
            font-family: monospace;
            color: #94a3b8;
        }
        @media (min-width: 768px) {
            .glass-card {
                max-width: 820px;
                padding: 2.5rem 3rem !important;
            }
            .gauge-container {
                height: 160px;
            }
            .gauge-bar {
                width: 28px;
            }
        }
        @media (max-width: 640px) {
            .glass-card {
                margin: 1rem;
                padding: 1.5rem !important;
                border-radius: 1.5rem;
            }
            .water-wave {
                display: none;
            }
            .gauge-container {
                height: 80px;
            }
            .gauge-bar {
                width: 16px;
            }
        }
        .skip-btn {
            background: rgba(59,130,246,0.15);
            border: 1px solid rgba(59,130,246,0.25);
            transition: all 0.2s;
        }
        .skip-btn:hover {
            background: rgba(59,130,246,0.25);
            border-color: rgba(59,130,246,0.5);
        }
    </style>
</head>
<body class="hero-bg min-h-screen flex items-center justify-center text-slate-100 p-4">

    <!-- Main Card -->
    <div class="glass-card rounded-3xl p-8 w-full max-w-4xl mx-auto text-center transition-all duration-500">

        <!-- Wave SVG decoration -->
        <svg class="water-wave" viewBox="0 0 1440 120" preserveAspectRatio="none">
            <path d="M0,60 C360,120 720,0 1080,60 C1260,90 1380,60 1440,80 L1440,120 L0,120 Z" fill="#3b82f6" opacity="0.3"/>
            <path d="M0,80 C240,40 480,100 720,80 C960,60 1200,100 1440,70 L1440,120 L0,120 Z" fill="#14b8a6" opacity="0.2"/>
        </svg>

        <!-- Desktop: flex row; Mobile: column (default) -->
        <div class="flex flex-col md:flex-row md:items-start md:gap-8 relative z-10">

            <!-- Left Column: Branding & Welcome -->
            <div class="flex-1 text-center md:text-left">
                <div class="flex justify-center md:justify-start mb-4">
                    <div class="bg-gradient-to-br from-blue-600 to-teal-500 p-3 rounded-2xl shadow-lg shadow-blue-500/20">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                        </svg>
                    </div>
                </div>
                <h1 class="text-3xl md:text-4xl font-black bg-gradient-to-r from-blue-400 via-indigo-400 to-teal-400 bg-clip-text text-transparent tracking-tight">
                    FloodMind Kelani
                </h1>
                <p class="text-xs font-mono uppercase tracking-widest text-slate-400 mt-1">Early Warning System · Real‑Time Telemetry</p>

                <div class="mt-6 space-y-1">
                    <p class="text-sm text-slate-300 leading-relaxed">
                        Welcome to the <span class="text-blue-400 font-semibold">Kelani River</span> flood monitoring hub.
                    </p>
                    <p class="text-xs text-slate-400">
                        Loading live hydrological data & predictive models…
                    </p>
                </div>

                <!-- Stats (2 columns) -->
                <div class="mt-6 grid grid-cols-2 gap-3 text-left">
                    <div class="bg-slate-900/40 border border-slate-800 rounded-xl p-3">
                        <div class="text-[9px] font-mono uppercase tracking-widest text-slate-400">Current Level</div>
                        <div class="text-xl font-black text-blue-400 stat-value flex items-center gap-1">
                            <?= number_format((float)$currentLevel['scaled_depth_meters'], 2) ?> m
                            <span style="color: <?= $trendColor ?>; font-size: 1rem;"><?= $trendIcon ?></span>
                        </div>
                        <div class="text-[9px] font-mono text-slate-500 mt-0.5">
                            <?= date('H:i', strtotime($currentLevel['recorded_at'])) ?>
                        </div>
                    </div>
                    <div class="bg-slate-900/40 border border-slate-800 rounded-xl p-3">
                        <div class="text-[9px] font-mono uppercase tracking-widest text-slate-400">Forecast (24h)</div>
                        <div class="text-xl font-black text-teal-400 stat-value">
                            <?= number_format((float)$forecast['expected_rainfall_mm'], 1) ?> mm
                        </div>
                        <div class="text-[9px] font-mono text-slate-500 mt-0.5">
                            <?= date('d M', strtotime($forecast['forecast_date'])) ?>
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap justify-center md:justify-start gap-3 text-xs font-mono text-slate-400">
                    <span>🌡️ <?= number_format((float)$forecast['expected_temp_c'], 1) ?>°C</span>
                    <span>💧 <?= (int)$forecast['expected_humidity'] ?>%</span>
                </div>
            </div>

            <!-- Right Column: Gauge Visual & Loading -->
            <div class="flex-1 mt-6 md:mt-0 flex flex-col items-center md:items-end">
                <!-- Gauge -->
                <div class="w-full max-w-[200px] md:max-w-[220px]">
                    <div class="flex justify-between text-[9px] font-mono text-slate-500 px-1">
                        <span>0.0</span>
                        <span>3.0 m</span>
                    </div>
                    <div class="gauge-container">
                        <?php
                        $level = (float)$currentLevel['scaled_depth_meters'];
                        $maxLevel = 3.0;
                        $heightPercent = min(($level / $maxLevel) * 100, 100);
                        ?>
                        <div class="gauge-bar fill" style="height: <?= $heightPercent ?>%;"></div>
                        <div class="gauge-bar" style="height: 20%;"></div>
                        <div class="gauge-bar" style="height: 40%;"></div>
                        <div class="gauge-bar" style="height: 60%;"></div>
                        <div class="gauge-bar" style="height: 80%;"></div>
                        <div class="gauge-bar fill" style="height: <?= $heightPercent ?>%;"></div>
                    </div>
                    <div class="text-center text-[10px] font-mono text-slate-400 mt-1">
                        Current water level
                    </div>
                </div>

                <!-- Loading Progress & Skip -->
                <div class="w-full mt-4 md:mt-6">
                    <div class="flex justify-between text-[10px] font-mono text-slate-500">
                        <span>Initialising telemetry</span>
                        <span id="progressPercent">0%</span>
                    </div>
                    <div class="progress-bar-track mt-1">
                        <div id="progressFill" class="progress-fill"></div>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-[9px] font-mono text-slate-500" id="countdownTimer">4s</span>
                        <button id="skipButton" class="skip-btn text-[10px] font-mono px-3 py-1 rounded-lg text-blue-300 transition-all cursor-pointer">
                            Skip →
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-6 pt-4 border-t border-slate-800/60 text-[10px] font-mono text-slate-500 flex flex-col sm:flex-row justify-between items-center gap-2 relative z-10">
            <span>© <?= date('Y') ?> FloodMind Kelani EWS</span>
            <span>Developed by <span class="text-blue-400 font-bold">Vexel IT</span> by <span class="text-slate-300">kavizz</span></span>
        </div>
    </div>

    <script>
        (function() {
            const progressFill = document.getElementById('progressFill');
            const progressPercent = document.getElementById('progressPercent');
            const countdownEl = document.getElementById('countdownTimer');
            const skipBtn = document.getElementById('skipButton');
            let progress = 0;
            const target = 100;
            const stepTime = 40;
            let startTime = Date.now();
            const totalDuration = 4000; // 4 seconds

            function updateProgress() {
                const elapsed = Date.now() - startTime;
                const pct = Math.min((elapsed / totalDuration) * 100, 100);
                progress = Math.floor(pct);
                progressFill.style.width = progress + '%';
                progressPercent.textContent = progress + '%';
                const remaining = Math.max(0, Math.ceil((totalDuration - elapsed) / 1000));
                countdownEl.textContent = remaining + 's';

                if (progress < 100) {
                    requestAnimationFrame(updateProgress);
                } else {
                    window.location.href = 'dashboard.php';
                }
            }

            // Start after a small delay
            setTimeout(updateProgress, 300);

            // Skip button
            skipBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                window.location.href = 'dashboard.php';
            });

            // Also click on card to skip (but not on interactive elements)
            document.querySelector('.glass-card').addEventListener('click', function(e) {
                if (e.target.closest('button') || e.target.closest('a')) return;
                window.location.href = 'dashboard.php';
            });
        })();
    </script>
</body>
</html>