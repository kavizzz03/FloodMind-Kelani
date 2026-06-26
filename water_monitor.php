<?php
// water_monitor.php
date_default_timezone_set('Asia/Colombo');

$host = '127.0.0.1'; 
$db   = 'floodmind kelani'; 
$user = 'root'; 
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    echo "<div class='text-xs text-rose-500 font-mono p-4 bg-rose-950/20 border border-rose-900 rounded-xl'>Telemetry Cluster Offline</div>";
    exit;
}

// Pull master stations along with their distinct threshold configurations and latest live logs
$query = "
    SELECT s.*, log.water_level, log.recorded_at, log.alert_status
    FROM stations s
    LEFT JOIN (
        SELECT l1.*
        FROM water_level_logs l1
        INNER JOIN (
            SELECT station_id, MAX(recorded_at) as max_time
            FROM water_level_logs
            GROUP BY station_id
        ) l2 ON l1.station_id = l2.station_id AND l1.recorded_at = l2.max_time
    ) log ON s.id = log.station_id
    ORDER BY s.id ASC
";
$activeNodes = $pdo->query($query)->fetchAll();
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 font-mono">
    <?php foreach ($activeNodes as $node): 
        $currentLevel = isset($node['water_level']) ? floatval($node['water_level']) : 0.0;
        
        // Defensive Fallbacks: If database keys are missing or uninitialized, fallback safely
        $unit         = $node['unit'] ?? 'm';
        $alertLimit   = floatval($node['alert_level'] ?? 1.22);
        $minorLimit   = floatval($node['minor_level'] ?? 1.52);
        $majorLimit   = floatval($node['major_level'] ?? 2.13);

        // Safety verification: Prevent layout breaks if limits accidentally register as zero
        if ($majorLimit <= 0) {
            $alertLimit = 1.22; $minorLimit = 1.52; $majorLimit = 2.13;
        }

        // Clean up historical foot entries if they bypass the trigger before conversion
        if ($node['id'] == 1 && $currentLevel > 20.0) {
            $currentLevel = round($currentLevel * 0.3048, 2); 
        }

        // Evaluation tree driven completely by individual metric nodes
        if ($currentLevel >= $majorLimit) {
            $statusText = "🚨 MAJOR FLOOD";
            $statusColor = "text-rose-400 bg-rose-950/60 border-rose-500";
            $barColor = "bg-rose-500";
        } elseif ($currentLevel >= $minorLimit) {
            $statusText = "⚠️ MINOR FLOOD";
            $statusColor = "text-amber-400 bg-amber-950/60 border-amber-500";
            $barColor = "bg-amber-500";
        } elseif ($currentLevel >= $alertLimit) {
            $statusText = "📊 NEAR ALERT LEVEL";
            $statusColor = "text-cyan-400 bg-cyan-950/60 border-cyan-500";
            $barColor = "bg-cyan-500 animate-pulse";
        } else {
            $statusText = "✅ NORMAL LEVEL";
            $statusColor = "text-emerald-400 bg-emerald-950/60 border-emerald-500";
            $barColor = "bg-emerald-500";
        }

        // Percentage tracking relative to the specific station's structural maximum ceiling
        $percentage = ($majorLimit > 0) ? ($currentLevel / $majorLimit) * 100 : 0;
        if ($percentage > 100) $percentage = 100;
    ?>
        <div class="bg-slate-900 border border-slate-800/80 rounded-2xl p-5 shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-start gap-2">
                    <div>
                        <h4 class="text-sm font-black text-slate-200"><?= htmlspecialchars($node['station_name']) ?></h4>
                        <p class="text-[10px] text-slate-500 mt-0.5">Limits (m): A:<?= number_format($alertLimit, 2) ?> | Mi:<?= number_format($minorLimit, 2) ?> | Ma:<?= number_format($majorLimit, 2) ?></p>
                    </div>
                    <span class="text-[9px] px-2.5 py-1 rounded-md border font-black tracking-wider <?= $statusColor ?>">
                        <?= $statusText ?>
                    </span>
                </div>

                <div class="mt-4 bg-slate-950/60 p-4 rounded-xl border border-slate-900 flex justify-between items-baseline">
                    <span class="text-xs text-slate-500 uppercase tracking-widest">Gauge Height</span>
                    <div>
                        <span class="text-3xl font-black text-slate-100"><?= number_format($currentLevel, 2) ?></span>
                        <span class="text-xs font-bold text-slate-400 ml-0.5"><?= $unit ?></span>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="flex justify-between text-[10px] text-slate-500 mb-1">
                        <span>Baseline</span>
                        <span>Major Cap (<?= number_format($majorLimit, 2) ?>m)</span>
                    </div>
                    <div class="w-full bg-slate-950 h-2 rounded-full overflow-hidden border border-slate-900">
                        <div class="h-full <?= $barColor ?> transition-all duration-500" style="width: <?= $percentage ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-800/60 flex justify-between text-[9px] text-slate-500">
                <span>System Node Pipeline Sync</span>
                <span><?= isset($node['recorded_at']) ? date('Y-m-d H:i', strtotime($node['recorded_at'])) : 'No Active Feed' ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>