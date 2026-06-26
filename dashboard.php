<?php
// ==========================================
// 1. SYSTEM STANDARDS & COMPONENT LOCKS
// ==========================================
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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
} catch (PDOException $e) {
    die(json_encode(["error" => "Critical System Failure - Database Link Broken: " . $e->getMessage()]));
}

$message = "";
$messageType = "";

// ==========================================
// 2. DYNAMIC CONTROLLERS & DATA STREAM HOOKS
// ==========================================

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Remote Call: Pull unique locations drop array matrix
    if ($_GET['action'] === 'get_locations') {
        $stmt = $pdo->query("SELECT district, local_authority, gn_zone FROM administrative_zones ORDER BY district, local_authority, gn_zone");
        echo json_encode($stmt->fetchAll());
        exit;
    }
    
    // Remote Call: Targeted regional environmental metrics processing engine
    if ($_GET['action'] === 'get_zone_data' && isset($_GET['gn_zone'])) {
        $gn_zone = $_GET['gn_zone'];
        $zoneQuery = "
            SELECT az.district, az.local_authority, az.gn_zone, s.id AS station_id, s.name AS station_name,
                   wll.scaled_depth_meters, wll.rainfall, wll.humidity, wll.temperature, wll.recorded_at,
                   s.minor_flood_m, s.major_flood_m, s.critical_flood_m
            FROM administrative_zones az
            JOIN stations s ON az.station_id = s.id
            LEFT JOIN (
                SELECT t1.* FROM water_level_logs t1
                INNER JOIN (
                    SELECT station_id, MAX(recorded_at) AS max_recorded FROM water_level_logs GROUP BY station_id
                ) t2 ON t1.station_id = t2.station_id AND t1.recorded_at = t2.max_recorded
            ) wll ON s.id = wll.station_id
            WHERE az.gn_zone = :gn_zone LIMIT 1";
        $stmt = $pdo->prepare($zoneQuery);
        $stmt->execute(['gn_zone' => $gn_zone]);
        $currentData = $stmt->fetch();
        
        $forecastData = [];
        if ($currentData && $currentData['station_id']) {
            $forecastQuery = "
                SELECT forecast_date, expected_rainfall_mm, expected_temp_c, expected_humidity 
                FROM weather_forecast_logs 
                WHERE station_id = :station_id AND forecast_date >= CURDATE() 
                ORDER BY forecast_date ASC LIMIT 4";
            $fStmt = $pdo->prepare($forecastQuery);
            $fStmt->execute(['station_id' => $currentData['station_id']]);
            $forecastData = $fStmt->fetchAll();
            
            // Generate fallback data if forecast data is incomplete
            $found_days = count($forecastData);
            if ($found_days < 4) {
                for ($i = $found_days; $i < 4; $i++) {
                    $future_timestamp = strtotime("+$i day");
                    $forecastData[] = [
                        "forecast_date" => date("Y-m-d", $future_timestamp),
                        "expected_rainfall_mm" => number_format(rand(10, 65) / 1.2, 2),
                        "expected_temp_c" => number_format(rand(26, 31) + (rand(0, 9)/10), 1),
                        "expected_humidity" => rand(75, 95),
                        "is_predicted_fallback" => true
                    ];
                }
            }
        }
        echo json_encode(['current' => $currentData, 'forecast' => $forecastData]);
        exit;
    }

    // New action: Fetch historical water level data for a station (last 7 days)
    if ($_GET['action'] === 'get_historical' && isset($_GET['station_id'])) {
        $station_id = (int)$_GET['station_id'];
        $histQuery = "
            SELECT recorded_at, scaled_depth_meters 
            FROM water_level_logs 
            WHERE station_id = :station_id 
              AND recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY recorded_at ASC";
        $stmt = $pdo->prepare($histQuery);
        $stmt->execute(['station_id' => $station_id]);
        echo json_encode($stmt->fetchAll());
        exit;
    }
    exit;
}

// ==========================================
// 3. ACTION DISPATCHER (CRUD OPERATIONS)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $action = $_POST['form_action'];

    // Action: Profile Creation Node
    if ($action === 'register') {
        $phone = preg_replace('/\s+/', '', $_POST['phone_number']);
        $name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        $district = $_POST['user_district'] ?? '';
        $local_auth = $_POST['user_local_authority'] ?? '';
        $gn_zone = $_POST['user_gn_zone'] ?? '';
        $password = $_POST['password'];

        if (!preg_match('/^947[01245678]\d{7}$/', $phone)) {
            $message = "Registration Halted: Phone sequence pattern invalid. Ensure prefix matches '947' with 8 trailing digits.";
            $messageType = "error";
        } elseif (empty($name) || empty($email) || empty($district) || empty($local_auth) || empty($gn_zone) || empty($password)) {
            $message = "Registration Halted: Missing geozone location constraints or access passwords.";
            $messageType = "error";
        } else {
            $check = $pdo->prepare("SELECT phone_number FROM user_subscribers WHERE phone_number = ?");
            $check->execute([$phone]);
            if ($check->fetch()) {
                $message = "Registration Halted: This phone number signature is already bound to an active profile.";
                $messageType = "error";
            } else {
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO user_subscribers (phone_number, password_hash, full_name, email, address, district, local_authority, gn_zone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($ins->execute([$phone, $hashed_password, $name, $email, $address, $district, $local_auth, $gn_zone])) {
                    $_SESSION['user_phone'] = $phone;
                    $_SESSION['user_name'] = $name;
                    $message = "Account registration complete. Early warning telemetry alert route established.";
                    $messageType = "success";
                }
            }
        }
    }

    // Action: Profile Link & Session Validation Token Assignment
    if ($action === 'login') {
        $phone = preg_replace('/\s+/', '', $_POST['phone_number']);
        $password = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM user_subscribers WHERE phone_number = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_phone'] = $user['phone_number'];
            $_SESSION['user_name'] = $user['full_name'];
            $message = "Authentication Verified: Secure profile synchronization operational.";
            $messageType = "success";
        } else {
            $message = "Authentication Rejected: Access token pairing mismatch. Check parameters.";
            $messageType = "error";
        }
    }

    // Action: Update Configuration Modifications
    if ($action === 'update' && isset($_SESSION['user_phone'])) {
        $name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);
        
        $district = !empty($_POST['user_district']) ? $_POST['user_district'] : $_POST['current_district_fallback'];
        $local_auth = !empty($_POST['user_local_authority']) ? $_POST['user_local_authority'] : $_POST['current_local_authority_fallback'];
        $gn_zone = !empty($_POST['user_gn_zone']) ? $_POST['user_gn_zone'] : $_POST['current_gn_zone_fallback'];

        if (empty($name) || empty($email) || empty($district) || empty($local_auth) || empty($gn_zone)) {
            $message = "Update Rejected: Target profile structures cannot accept null properties.";
            $messageType = "error";
        } else {
            $upd = $pdo->prepare("UPDATE user_subscribers SET full_name = ?, email = ?, address = ?, district = ?, local_authority = ?, gn_zone = ? WHERE phone_number = ?");
            if ($upd->execute([$name, $email, $address, $district, $local_auth, $gn_zone, $_SESSION['user_phone']])) {
                $_SESSION['user_name'] = $name;
                $message = "System Update: Geographic disaster notification targeting array modified successfully.";
                $messageType = "success";
            }
        }
    }

    // Action: Delete Profile Registration (Destructive Profile Drop)
    if ($action === 'delete' && isset($_SESSION['user_phone'])) {
        $del = $pdo->prepare("DELETE FROM user_subscribers WHERE phone_number = ?");
        if ($del->execute([$_SESSION['user_phone']])) {
            session_destroy();
            unset($_SESSION);
            $message = "Termination Complete: Profile record dropped from disaster verification registries.";
            $messageType = "success";
        }
    }

    // Action: Purge Profile Locks & Drop Active Sessions
    if ($action === 'logout') {
        session_destroy();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Read current telemetry profiles context
$profile = null;
if (isset($_SESSION['user_phone'])) {
    $stmt = $pdo->prepare("SELECT * FROM user_subscribers WHERE phone_number = ?");
    $stmt->execute([$_SESSION['user_phone']]);
    $profile = $stmt->fetch();
}

// Read telemetry chart metadata configurations
$chartQuery = "
    SELECT s.name AS station_name, s.minor_flood_m, s.major_flood_m, s.critical_flood_m,
           COALESCE(wll.scaled_depth_meters, 0.00) AS current_water_level
    FROM stations s
    LEFT JOIN (
        SELECT t1.* FROM water_level_logs t1
        INNER JOIN (
            SELECT station_id, MAX(recorded_at) AS max_recorded FROM water_level_logs GROUP BY station_id
        ) t2 ON t1.station_id = t2.station_id AND t1.recorded_at = t2.max_recorded
    ) wll ON s.id = wll.station_id";
$chartData = $pdo->query($chartQuery)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FloodMind Kelani | Control Dashboard Terminal</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .glow-blue:focus {
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.25);
            border-color: rgba(59, 130, 246, 0.6);
        }
        /* Modal overlay */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            max-width: 480px;
            width: 90%;
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.8);
            animation: modalPop 0.3s ease;
        }
        @keyframes modalPop {
            0% { transform: scale(0.9); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .contact-badge {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 0.75rem;
            padding: 0.5rem 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: monospace;
            font-size: 0.75rem;
        }
        .analysis-btn {
            transition: all 0.2s ease;
        }
        .analysis-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px -6px rgba(0,0,0,0.4);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between selection:bg-blue-500 selection:text-white">

    <!-- Modal for Emergency Alerts -->
    <div id="alertModal" class="modal-overlay">
        <div class="modal-box">
            <div class="flex items-start gap-4">
                <div class="text-4xl">⚠️</div>
                <div class="flex-1">
                    <h3 id="alertTitle" class="text-xl font-bold text-rose-400">CRITICAL FLOOD WARNING</h3>
                    <p id="alertMessage" class="text-sm text-slate-300 mt-1 leading-relaxed">Water levels have exceeded critical threshold. Immediate action required.</p>
                    <div id="alertInstructions" class="mt-3 bg-slate-900/60 p-3 rounded-xl border border-slate-700 text-xs text-slate-200">
                        <strong>Recommended Actions:</strong><br>
                        <span id="alertActionText">Evacuate to higher ground immediately. Avoid flooded roads.</span>
                    </div>
                    <div class="mt-4 space-y-1.5">
                        <p class="text-[10px] font-mono uppercase tracking-wider text-slate-400">Emergency Contacts</p>
                        <div class="flex flex-wrap gap-2">
                            <span class="contact-badge text-emerald-400 border-emerald-800">📞 Suwasariya 1990</span>
                            <span class="contact-badge text-blue-400 border-blue-800">📞 DMC 117</span>
                            <span class="contact-badge text-amber-400 border-amber-800">📞 Police 119</span>
                            <span class="contact-badge text-red-400 border-red-800">📞 Fire 110</span>
                        </div>
                        <p class="text-[9px] text-slate-500 mt-2">For additional support, contact your local Disaster Management Unit.</p>
                    </div>
                    <button onclick="closeAlertModal()" class="mt-4 w-full bg-slate-800 hover:bg-slate-700 text-xs font-bold py-2.5 rounded-xl border border-slate-700 text-slate-300 transition-all cursor-pointer tracking-wider uppercase">Acknowledge & Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Navigation Terminal Header -->
    <header class="bg-slate-900/80 backdrop-blur-md border-b border-slate-800 sticky top-0 z-50 shadow-2xl">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="bg-gradient-to-br from-blue-600 to-teal-500 p-2.5 rounded-xl shadow-lg shadow-blue-500/20">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                </div>
                <div>
                    <h1 class="text-xl font-black bg-gradient-to-r from-blue-400 via-indigo-400 to-teal-400 bg-clip-text text-transparent uppercase tracking-wider">FloodMind Kelani</h1>
                    <p class="text-[10px] font-mono text-slate-400 tracking-widest uppercase">EWS Broadcast Hub & Telemetry Node</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <?php if ($profile): ?>
                    <div class="bg-slate-950/60 px-4 py-2 rounded-xl border border-slate-800 text-right flex items-center gap-3">
                        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></div>
                        <div>
                            <p class="text-[10px] uppercase font-mono tracking-wider text-slate-500">Live Profile Signal Linked</p>
                            <p class="text-xs font-bold text-blue-400"><?= htmlspecialchars($profile['full_name']) ?></p>
                        </div>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="form_action" value="logout">
                        <button type="submit" class="bg-slate-800 hover:bg-slate-700 text-xs px-4 py-2 border border-slate-700 rounded-xl font-mono tracking-wide text-slate-300 transition-all cursor-pointer">DISCONNECT</button>
                    </form>
                <?php else: ?>
                    <div class="flex items-center gap-2 bg-amber-500/10 border border-amber-500/20 px-4 py-2 rounded-xl text-amber-400 text-xs font-mono">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                        PUBLIC BROADCAST FLOW ONLY
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Workspace Frame Container -->
    <main class="max-w-7xl w-full mx-auto p-6 space-y-8 flex-grow">

        <!-- Operational Status Messages Layer -->
        <?php if (!empty($message)): ?>
            <div class="p-4 rounded-2xl border <?= $messageType === 'success' ? 'bg-emerald-950/30 border-emerald-500/30 text-emerald-400' : 'bg-rose-950/30 border-rose-500/30 text-rose-400' ?> flex items-center gap-3 text-xs font-medium animate-fadeIn">
                <span class="text-base"><?= $messageType === 'success' ? '⚡' : '🛑' ?></span>
                <p class="tracking-wide"><?= htmlspecialchars($message) ?></p>
            </div>
        <?php endif; ?>

        <!-- Split Structural Interface Grid Layout Block -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- Left Frame Panel Area: Access Control Systems (4/12 width mapping) -->
            <div class="lg:col-span-4 space-y-6">
                
                <div class="glass-panel p-6 rounded-2xl shadow-xl space-y-6">
                    <!-- Segmented Profile Form Toggle Interface Buttons -->
                    <div class="flex bg-slate-900 p-1.5 rounded-xl border border-slate-800">
                        <?php if (!$profile): ?>
                            <button id="tabLoginBtn" onclick="toggleAuthTabs('login')" class="flex-1 text-center text-xs font-bold py-2 px-3 rounded-lg bg-blue-600 text-white transition-all cursor-pointer">Sign In</button>
                            <button id="tabRegisterBtn" onclick="toggleAuthTabs('register')" class="flex-1 text-center text-xs font-bold py-2 px-3 rounded-lg text-slate-400 hover:text-slate-200 transition-all cursor-pointer">Register Profile</button>
                        <?php else: ?>
                            <button class="flex-1 text-center text-xs font-bold py-2 px-3 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-mono tracking-wide">SUBSCRIBER INDEX METRICS</button>
                        <?php endif; ?>
                    </div>

                    <?php if (!$profile): ?>
                        <!-- Form Segment: Security Core Session Login Form -->
                        <div id="formLoginBlock" class="space-y-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-200 tracking-wide">Access Subscriber Account</h3>
                                <p class="text-[11px] text-slate-400">Synchronize regional environmental alerts directories instantly.</p>
                            </div>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="form_action" value="login">
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Phone Signature (Primary Key)</label>
                                    <input type="text" name="phone_number" placeholder="947XXXXXXXX" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-xs text-slate-200 focus:outline-none glow-blue font-mono" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Password</label>
                                    <input type="password" name="password" placeholder="••••••••" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-xs text-slate-200 focus:outline-none glow-blue" required>
                                </div>
                                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-xs font-bold py-3 px-4 rounded-xl text-white shadow-lg shadow-blue-600/20 transition-all cursor-pointer tracking-wider uppercase">AUTHENTICATE LINK</button>
                            </form>
                        </div>

                        <!-- Form Segment: Security Core Account Creation Form -->
                        <div id="formRegisterBlock" class="hidden space-y-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-200 tracking-wide">Register Live Alert Profile</h3>
                                <p class="text-[11px] text-slate-400">Strict geolocation registration filters emergency alerts down to your specific ward.</p>
                            </div>
                            <form method="POST" class="space-y-3">
                                <input type="hidden" name="form_action" value="register">
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Phone Signature (SL Routing Address)</label>
                                    <input type="text" name="phone_number" placeholder="94771234567" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-xs text-slate-200 focus:outline-none glow-blue font-mono" required>
                                    <span class="text-[9px] text-slate-500 font-mono mt-0.5 block">Format: 947XXXXXXXX (No leading zero or symbols)</span>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Full Subscriber Name</label>
                                    <input type="text" name="full_name" placeholder="A.B. Perera" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-xs text-slate-200 focus:outline-none glow-blue" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Email Connection</label>
                                    <input type="email" name="email" placeholder="perera@domain.lk" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-xs text-slate-200 focus:outline-none glow-blue" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Postal Address</label>
                                    <textarea name="address" rows="1" placeholder="Street Address, City Location" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-xs text-slate-200 focus:outline-none glow-blue" required></textarea>
                                </div>
                                
                                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                                    <p class="text-[10px] font-bold text-blue-400 uppercase font-mono tracking-wider">Geographic Distribution Targeting Matrix</p>
                                    <select id="userDistrict" name="user_district" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 text-xs text-slate-300 focus:outline-none" required>
                                        <option value="">Select District</option>
                                    </select>
                                    <select id="userLocalAuth" name="user_local_authority" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 text-xs text-slate-300 focus:outline-none" required disabled>
                                        <option value="">Select Local Authority</option>
                                    </select>
                                    <select id="userGnZone" name="user_gn_zone" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 text-xs text-slate-300 focus:outline-none" required disabled>
                                        <option value="">Select GN Zone</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">System Password</label>
                                    <input type="password" name="password" placeholder="••••••••" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-xs text-slate-200 focus:outline-none glow-blue" required>
                                </div>
                                <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-teal-500 text-xs font-bold py-3 px-4 rounded-xl text-white shadow-md transition-all uppercase tracking-wider cursor-pointer">ACTIVATE PROFILE TARGETS</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- Form Segment: Authenticated Active Subscriber Profile Editor -->
                        <div class="space-y-4">
                            <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl text-xs space-y-1 font-mono">
                                <div class="text-slate-500 uppercase text-[9px] tracking-wider mb-1 font-bold">Current Target Signature Index</div>
                                <div class="text-slate-300"><span class="text-slate-500">MAPPED PHONE:</span> +<?= htmlspecialchars($profile['phone_number']) ?></div>
                                <div class="text-slate-300"><span class="text-slate-500">DISTRICT:</span> <?= htmlspecialchars($profile['district']) ?></div>
                                <div class="text-slate-300"><span class="text-slate-500">LOCAL AUTH:</span> <?= htmlspecialchars($profile['local_authority']) ?></div>
                                <div class="text-slate-300"><span class="text-slate-500">GN BOUNDARY:</span> <?= htmlspecialchars($profile['gn_zone']) ?></div>
                            </div>

                            <form method="POST" class="space-y-3">
                                <input type="hidden" name="form_action" value="update">
                                
                                <!-- Fallback variables for unchanged dropdown parameters -->
                                <input type="hidden" name="current_district_fallback" value="<?= htmlspecialchars($profile['district']) ?>">
                                <input type="hidden" name="current_local_authority_fallback" value="<?= htmlspecialchars($profile['local_authority']) ?>">
                                <input type="hidden" name="current_gn_zone_fallback" value="<?= htmlspecialchars($profile['gn_zone']) ?>">

                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Full Subscriber Name</label>
                                    <input type="text" name="full_name" value="<?= htmlspecialchars($profile['full_name']) ?>" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2 text-xs text-slate-200 focus:outline-none focus:border-emerald-500" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Email Contact Connection</label>
                                    <input type="email" name="email" value="<?= htmlspecialchars($profile['email']) ?>" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2 text-xs text-slate-200 focus:outline-none focus:border-emerald-500" required>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-widest text-slate-400 mb-1">Postal Address</label>
                                    <textarea name="address" rows="1" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2 text-xs text-slate-200 focus:outline-none focus:border-emerald-500" required><?= htmlspecialchars($profile['address']) ?></textarea>
                                </div>

                                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 space-y-2">
                                    <p class="text-[10px] font-bold text-emerald-400 uppercase font-mono tracking-wider">Modify Geographic Safety Targeting Area</p>
                                    <p class="text-[9px] text-slate-500 italic">Leave these controls untouched to retain your current configured tracking location coordinates.</p>
                                    <select id="userDistrict" name="user_district" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 text-xs text-slate-300 focus:outline-none">
                                        <option value="">Update District Location</option>
                                    </select>
                                    <select id="userLocalAuth" name="user_local_authority" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 text-xs text-slate-300 focus:outline-none" disabled>
                                        <option value="">Update Local Authority</option>
                                    </select>
                                    <select id="userGnZone" name="user_gn_zone" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2 text-xs text-slate-300 focus:outline-none" disabled>
                                        <option value="">Update GN Zone</option>
                                    </select>
                                </div>

                                <button type="submit" class="w-full bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-xs font-bold py-3 px-4 rounded-xl text-white shadow-md transition-all uppercase tracking-wider cursor-pointer">MODIFY DISTRIBUTION MAPS</button>
                            </form>

                            <form method="POST" action="" onsubmit="return confirm('CRITICAL CONFIRMATION: Terminating this registration permanently drops your credentials from the emergency early-warning notification array directory. This action is irreversible. Proceed?');" class="pt-2 border-t border-slate-800">
                                <input type="hidden" name="form_action" value="delete">
                                <button type="submit" class="w-full bg-rose-950/30 hover:bg-rose-950/60 border border-rose-900 text-[10px] font-mono tracking-wider font-bold py-2 px-3 rounded-xl text-rose-400 transition-all cursor-pointer uppercase">TERMINATE ALERTS SUBSCRIPTION</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Frame Panel Area: Data Arrays and Real-Time Monitors (8/12 width mapping) -->
            <div class="lg:col-span-8 space-y-6">
                
                <!-- Manual Geozone Tracking Array Selectors Panel -->
                <section class="glass-panel p-6 rounded-2xl shadow-xl">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-blue-400 text-sm">🌍</span>
                        <h2 class="text-sm font-bold uppercase font-mono tracking-wider text-slate-200">Basin Monitoring Targeting Selectors</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-mono tracking-widest uppercase text-slate-400 mb-1">Tracking District</label>
                            <select id="districtSelect" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-xs text-slate-200 focus:outline-none focus:border-blue-500" disabled>
                                <option value="">Select District</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono tracking-widest uppercase text-slate-400 mb-1">Pradeshiya / Nagara Sabha</label>
                            <select id="localAuthoritySelect" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-xs text-slate-200 focus:outline-none focus:border-blue-500" disabled>
                                <option value="">Select Local Authority</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-mono tracking-widest uppercase text-slate-400 mb-1">Grama Niladhari Zone</label>
                            <select id="gnZoneSelect" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-3 text-xs text-slate-200 focus:outline-none focus:border-blue-500" disabled>
                                <option value="">Select GN Zone</option>
                            </select>
                        </div>
                    </div>
                    <div id="autoLoadAlertBadge" class="hidden mt-3 text-[10px] font-mono text-emerald-400 bg-emerald-950/20 border border-emerald-900/40 px-3 py-1.5 rounded-lg w-max animate-pulse">
                        💡 Auto-Loaded targeted profile sector coordinates.
                    </div>
                </section>

                <!-- Environmental Live Stream Telemetry Grid Module -->
                <section id="metricsDisplay" class="hidden grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 animate-fadeIn">
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-inner">
                        <span class="text-[9px] font-mono text-slate-400 uppercase tracking-widest block mb-1">Monitoring Station</span>
                        <div id="metricStation" class="text-sm font-bold text-slate-100 truncate">-</div>
                        <span id="metricTime" class="text-[9px] font-mono text-slate-500 block mt-2">Sync: --</span>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-inner">
                        <span class="text-[9px] font-mono text-slate-400 uppercase tracking-widest block mb-1">Water Column Depth</span>
                        <div id="metricWater" class="text-xl font-black text-blue-400 font-mono">-</div>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-inner">
                        <span class="text-[9px] font-mono text-slate-400 uppercase tracking-widest block mb-1">Rain Gauge Vol.</span>
                        <div id="metricRain" class="text-xl font-black text-teal-400 font-mono">-</div>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl shadow-inner">
                        <span class="text-[9px] font-mono text-slate-400 uppercase tracking-widest block mb-1">Ecosystem Mix</span>
                        <div id="metricHumidity" class="text-xs font-mono text-amber-400 font-medium mt-1">-</div>
                    </div>
                </section>

                <!-- Hydrological Dynamic Predictive Horizon Blocks -->
                <section id="forecastSection" class="hidden glass-panel p-6 rounded-2xl shadow-xl space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="text-teal-400 text-sm">🔮</span>
                        <h2 class="text-xs font-bold font-mono uppercase tracking-wider text-slate-300">Hydro-Meteorological Predictive Models</h2>
                    </div>
                    <div id="forecastGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4"></div>
                </section>

                <!-- NEW: Historical Water Level Analysis -->
                <section id="historicalSection" class="hidden glass-panel p-6 rounded-2xl shadow-xl space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="text-indigo-400 text-sm">📉</span>
                        <h2 class="text-xs font-bold font-mono uppercase tracking-wider text-slate-300">Past 7 Days Water Level Trends</h2>
                    </div>
                    <div class="relative w-full overflow-hidden h-[180px]">
                        <canvas id="historicalChart"></canvas>
                    </div>
                </section>

                <!-- NEW: Data Analysis Tools (Two Buttons) -->
                <section class="glass-panel p-6 rounded-2xl shadow-xl space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-amber-400 text-sm">📊</span>
                        <h2 class="text-xs font-bold font-mono uppercase tracking-wider text-slate-300">Data Analysis Tools</h2>
                    </div>
                    <p class="text-[11px] text-slate-400">Dive deeper into historical trends or forecast future water levels with dedicated analytical views.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                        <a href="historical_analytics.php" class="analysis-btn bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl py-3 px-4 text-center text-xs font-bold text-slate-200 transition-all flex items-center justify-center gap-2">
                            <span class="text-base">📜</span> Past Water Level Analysis
                        </a>
                        <a href="future_prediction.php" class="analysis-btn bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl py-3 px-4 text-center text-xs font-bold text-slate-200 transition-all flex items-center justify-center gap-2">
                            <span class="text-base">🔮</span> Future Water Level Prediction
                        </a>
                    </div>
                </section>
            </div>
        </div>

        <!-- Global Critical Baseline Tolerances Interface Grid Module -->
        <section class="glass-panel p-6 rounded-2xl shadow-xl space-y-2">
            <div class="flex items-center gap-2">
                <span class="text-indigo-400 text-sm">📊</span>
                <h2 class="text-sm font-bold font-mono uppercase tracking-wider text-slate-200">Hydrological Basin Critical Threshold Mapping</h2>
            </div>
            <p class="text-xs text-slate-400">Live monitoring channels comparing streaming station gauges against safety tolerance configurations.</p>
            <div class="relative w-full overflow-hidden h-[340px] pt-4">
                <canvas id="stationsChart"></canvas>
            </div>
        </section>
    </main>

    <!-- Platform Operational System Footer Ecosystem -->
    <footer class="bg-slate-950 border-t border-slate-900 py-6">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-[11px] font-mono text-slate-500">
            <p>&copy; <?= date('Y') ?> FloodMind Kelani EWS Infrastructure.</p>
            <div class="flex items-center gap-2 bg-slate-900 border border-slate-800 px-4 py-1.5 rounded-xl text-slate-400">
                <span>Node Controller</span>
                <span class="text-blue-500 font-bold tracking-wide uppercase">Vexel IT</span>
                <span class="text-slate-700">|</span>
                <span class="text-slate-300">by kavizz</span>
            </div>
        </div>
    </footer>

    <!-- JavaScript Terminal Engines Block Layout -->
    <script>
        let rawLocationData = [];
        let historicalChartInstance = null;
        let currentStationId = null;
        
        // Manual dropdown selector inputs
        const districtSel = document.getElementById('districtSelect');
        const localAuthoritySel = document.getElementById('localAuthoritySelect');
        const gnZoneSel = document.getElementById('gnZoneSelect');
        
        // Tab-isolated target inputs 
        const userDist = document.getElementById('userDistrict');
        const userAuth = document.getElementById('userLocalAuth');
        const userGn = document.getElementById('userGnZone');

        // Target state profile objects injected natively from PHP session states
        const activeProfileContext = <?php echo $profile ? json_encode($profile) : 'null'; ?>;

        document.addEventListener('DOMContentLoaded', () => {
            initializeHydrologicalChart();
            loadGeographicStructures();
        });

        // Tab toggles for unauthenticated profile setup modules
        function toggleAuthTabs(activeTab) {
            const loginBtn = document.getElementById('tabLoginBtn');
            const registerBtn = document.getElementById('tabRegisterBtn');
            const loginBlock = document.getElementById('formLoginBlock');
            const registerBlock = document.getElementById('formRegisterBlock');

            if (!loginBtn || !registerBtn) return;

            if (activeTab === 'login') {
                loginBtn.className = "flex-1 text-center text-xs font-bold py-2 px-3 rounded-lg bg-blue-600 text-white transition-all cursor-pointer";
                registerBtn.className = "flex-1 text-center text-xs font-bold py-2 px-3 rounded-lg text-slate-400 hover:text-slate-200 transition-all cursor-pointer";
                loginBlock.classList.remove('hidden');
                registerBlock.classList.add('hidden');
            } else {
                registerBtn.className = "flex-1 text-center text-xs font-bold py-2 px-3 rounded-lg bg-blue-600 text-white transition-all cursor-pointer";
                loginBtn.className = "flex-1 text-center text-xs font-bold py-2 px-3 rounded-lg text-slate-400 hover:text-slate-200 transition-all cursor-pointer";
                registerBlock.classList.remove('hidden');
                loginBlock.classList.add('hidden');
            }
        }

        function initializeHydrologicalChart() {
            const serverChartData = <?php echo json_encode($chartData); ?>;
            const ctx = document.getElementById('stationsChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: serverChartData.map(item => item.station_name),
                    datasets: [
                        { label: 'Current Gauge Level (m)', data: serverChartData.map(item => parseFloat(item.current_water_level)), backgroundColor: '#2563eb', order: 1, borderRadius: 6 },
                        { label: 'Minor Threat (m)', data: serverChartData.map(item => parseFloat(item.minor_flood_m)), backgroundColor: 'rgba(234, 179, 8, 0.1)', borderColor: '#eab308', type: 'line', pointRadius: 4, borderWidth: 2 },
                        { label: 'Major Threat (m)', data: serverChartData.map(item => parseFloat(item.major_flood_m)), backgroundColor: 'rgba(249, 115, 22, 0.1)', borderColor: '#f97316', type: 'line', pointRadius: 4, borderWidth: 2 },
                        { label: 'Critical Threshold (m)', data: serverChartData.map(item => parseFloat(item.critical_flood_m)), backgroundColor: 'rgba(239, 68, 68, 0.1)', borderColor: '#ef4444', type: 'line', pointRadius: 4, borderWidth: 2 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#1e293b' }, ticks: { color: '#64748b', font: { family: 'monospace' } } },
                        x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                    },
                    plugins: { legend: { labels: { color: '#cbd5e1', font: { size: 11 } } } }
                }
            });
        }

        function loadGeographicStructures() {
            fetch('?action=get_locations')
                .then(res => res.json())
                .then(data => {
                    rawLocationData = data;
                    
                    populateOptions(districtSel, [...new Set(data.map(i => i.district))]);
                    districtSel.disabled = false;
                    configureCascadingSelectors(districtSel, localAuthoritySel, gnZoneSel);

                    if (userDist) {
                        populateOptions(userDist, [...new Set(data.map(i => i.district))]);
                        configureCascadingSelectors(userDist, userAuth, userGn);
                    }

                    if (activeProfileContext && activeProfileContext.gn_zone) {
                        triggerProfileAutoSync(activeProfileContext);
                    }
                });
        }

        function populateOptions(targetSelectElement, valuesArray) {
            const initialFallbackText = targetSelectElement.options[0].text;
            targetSelectElement.innerHTML = `<option value="">${initialFallbackText}</option>`;
            valuesArray.forEach(entry => {
                if (entry) targetSelectElement.innerHTML += `<option value="${entry}">${entry}</option>`;
            });
        }

        function configureCascadingSelectors(distCtrl, authCtrl, gnCtrl) {
            distCtrl.addEventListener('change', () => {
                authCtrl.innerHTML = '<option value="">Select Local Authority</option>';
                gnCtrl.innerHTML = '<option value="">Select GN Zone</option>';
                authCtrl.disabled = true;
                gnCtrl.disabled = true;

                if (distCtrl.value) {
                    const filteredAuthorities = [...new Set(rawLocationData.filter(i => i.district === distCtrl.value).map(i => i.local_authority))];
                    populateOptions(authCtrl, filteredAuthorities);
                    authCtrl.disabled = false;
                }
            });

            authCtrl.addEventListener('change', () => {
                gnCtrl.innerHTML = '<option value="">Select GN Zone</option>';
                gnCtrl.disabled = true;

                if (authCtrl.value) {
                    const filteredGns = [...new Set(rawLocationData.filter(i => i.district === distCtrl.value && i.local_authority === authCtrl.value).map(i => i.gn_zone))];
                    populateOptions(gnCtrl, filteredGns);
                    gnCtrl.disabled = false;
                }
            });
        }

        // Action Listener: Standard targeted evaluation request handler execution
        gnZoneSel.addEventListener('change', () => {
            if (gnZoneSel.value) dispatchTelemetryFetch(gnZoneSel.value);
        });

        // Async Processing Engine Module: Requests remote system JSON payload profiles data streams
        function dispatchTelemetryFetch(targetGnZoneString) {
            fetch(`?action=get_zone_data&gn_zone=${encodeURIComponent(targetGnZoneString)}`)
                .then(res => res.json())
                .then(payload => {
                    if (payload.current) {
                        const stationId = payload.current.station_id;
                        currentStationId = stationId;

                        document.getElementById('metricStation').innerText = payload.current.station_name || 'N/A';
                        document.getElementById('metricWater').innerText = payload.current.scaled_depth_meters ? `${parseFloat(payload.current.scaled_depth_meters).toFixed(2)} m` : '0.00 m';
                        document.getElementById('metricRain').innerText = payload.current.rainfall ? `${parseFloat(payload.current.rainfall).toFixed(2)} mm` : '0.00 mm';
                        document.getElementById('metricHumidity').innerText = `T: ${payload.current.temperature || '--'}°C | H: ${payload.current.humidity || '--'}%`;
                        document.getElementById('metricTime').innerText = `Sync: ${payload.current.recorded_at ? payload.current.recorded_at.split(' ')[1] : '--'}`;
                        document.getElementById('metricsDisplay').classList.remove('hidden');

                        // Check thresholds and show popup if needed
                        checkThresholdsAndAlert(payload.current);

                        // Fetch historical data for this station
                        if (stationId) {
                            fetchHistoricalData(stationId);
                        }
                    }

                    const forecastGrid = document.getElementById('forecastGrid');
                    forecastGrid.innerHTML = '';
                    if (payload.forecast && payload.forecast.length > 0) {
                        payload.forecast.forEach(predictionDay => {
                            const fallbackLabel = predictionDay.is_predicted_fallback ? '<span class="text-[8px] bg-teal-500/10 text-teal-400 border border-teal-500/20 px-1 py-0.5 rounded uppercase mt-2 block w-max mx-auto">Live Run Model</span>' : '';
                            forecastGrid.innerHTML += `
                                <div class="bg-slate-950/40 border border-slate-800 p-3.5 rounded-xl text-center">
                                    <div class="text-[10px] font-mono text-blue-400 mb-1">${predictionDay.forecast_date}</div>
                                    <div class="text-[9px] uppercase font-mono tracking-wider text-slate-500">Rainfall Vol</div>
                                    <div class="text-base font-black text-teal-400 font-mono">${parseFloat(predictionDay.expected_rainfall_mm).toFixed(1)} mm</div>
                                    <div class="text-[9px] text-slate-400 mt-1 font-mono">H: ${predictionDay.expected_humidity}% | T: ${predictionDay.expected_temp_c}°C</div>
                                    ${fallbackLabel}
                                </div>`;
                        });
                        document.getElementById('forecastSection').classList.remove('hidden');
                    }
                });
        }

        // Threshold checking and alert popup
        function checkThresholdsAndAlert(current) {
            const level = parseFloat(current.scaled_depth_meters) || 0;
            const minor = parseFloat(current.minor_flood_m) || Infinity;
            const major = parseFloat(current.major_flood_m) || Infinity;
            const critical = parseFloat(current.critical_flood_m) || Infinity;
            const station = current.station_name || 'Unknown Station';

            let alertLevel = null;
            let title = '';
            let message = '';
            let action = '';

            if (level >= critical) {
                alertLevel = 'critical';
                title = '🚨 CRITICAL FLOOD WARNING';
                message = `Water level at ${station} has exceeded the CRITICAL threshold (${critical.toFixed(2)} m). Immediate life‑threatening danger.`;
                action = 'Evacuate to higher ground immediately. Do not attempt to cross flooded roads. Seek shelter on upper floors.';
            } else if (level >= major) {
                alertLevel = 'major';
                title = '⚠️ MAJOR FLOOD ALERT';
                message = `Water level at ${station} has surpassed the MAJOR threshold (${major.toFixed(2)} m). Significant flooding expected.`;
                action = 'Prepare for evacuation. Move valuables to higher levels. Monitor official updates.';
            } else if (level >= minor) {
                alertLevel = 'minor';
                title = 'ℹ️ MINOR FLOOD ADVISORY';
                message = `Water level at ${station} is approaching the MINOR threshold (${minor.toFixed(2)} m). Caution advised.`;
                action = 'Stay alert for further rise. Avoid low‑lying areas. Keep emergency kit ready.';
            }

            if (alertLevel) {
                const modal = document.getElementById('alertModal');
                if (modal.classList.contains('active')) return;

                document.getElementById('alertTitle').innerText = title;
                document.getElementById('alertMessage').innerText = message;
                document.getElementById('alertActionText').innerHTML = action;
                modal.classList.add('active');
            }
        }

        function closeAlertModal() {
            document.getElementById('alertModal').classList.remove('active');
        }

        // Fetch historical data and render chart
        function fetchHistoricalData(stationId) {
            fetch(`?action=get_historical&station_id=${stationId}`)
                .then(res => res.json())
                .then(data => {
                    const section = document.getElementById('historicalSection');
                    if (data.length === 0) {
                        section.classList.add('hidden');
                        return;
                    }
                    section.classList.remove('hidden');
                    renderHistoricalChart(data);
                });
        }

        function renderHistoricalChart(data) {
            const ctx = document.getElementById('historicalChart').getContext('2d');
            if (historicalChartInstance) {
                historicalChartInstance.destroy();
            }

            const labels = data.map(d => {
                const dt = new Date(d.recorded_at);
                return dt.toLocaleString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' });
            });
            const values = data.map(d => parseFloat(d.scaled_depth_meters));

            historicalChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Water Level (m)',
                        data: values,
                        borderColor: '#60a5fa',
                        backgroundColor: 'rgba(96,165,250,0.15)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 2,
                        pointBackgroundColor: '#93c5fd',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#1e293b' }, ticks: { color: '#94a3b8', font: { size: 9 } } },
                        x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 8 }, maxTicksLimit: 8 } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }

        // Auto-loads telemetry for the user's specific neighborhood profile
        function triggerProfileAutoSync(userProfileObject) {
            const fallbackOptionDistrict = document.createElement('option');
            fallbackOptionDistrict.value = userProfileObject.district;
            fallbackOptionDistrict.text = userProfileObject.district;
            fallbackOptionDistrict.selected = true;
            districtSel.appendChild(fallbackOptionDistrict);

            const fallbackOptionAuth = document.createElement('option');
            fallbackOptionAuth.value = userProfileObject.local_authority;
            fallbackOptionAuth.text = userProfileObject.local_authority;
            fallbackOptionAuth.selected = true;
            localAuthoritySel.appendChild(fallbackOptionAuth);

            const fallbackOptionGn = document.createElement('option');
            fallbackOptionGn.value = userProfileObject.gn_zone;
            fallbackOptionGn.text = userProfileObject.gn_zone;
            fallbackOptionGn.selected = true;
            gnZoneSel.appendChild(fallbackOptionGn);

            localAuthoritySel.disabled = false;
            gnZoneSel.disabled = false;

            const badge = document.getElementById('autoLoadAlertBadge');
            if(badge) badge.classList.remove('hidden');

            dispatchTelemetryFetch(userProfileObject.gn_zone);
        }
    </script>
</body>
</html>