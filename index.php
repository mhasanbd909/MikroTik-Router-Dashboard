<?php
/**
 * MikroTik Router Dashboard
 * 
 * Real-time monitoring interface for MikroTik routers
 */

session_start();
require_once 'MikroTikAPI.php';

$error = '';
$success = false;

// Get credentials from form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['connect'])) {
    $host = $_POST['host'] ?? ROUTER_HOST;
    $port = $_POST['port'] ?? ROUTER_PORT;
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    if (empty($user) || empty($pass)) {
        $error = 'Please provide both username and password.';
    } else {
        try {
            // Test connection
            $api = new MikroTikAPI($host, (int)$port, $user, $pass, DEBUG_MODE);
            $api->connect();
            $api->disconnect();
            
            // Save to session
            $_SESSION['router_host'] = $host;
            $_SESSION['router_port'] = (int)$port;
            $_SESSION['router_user'] = $user;
            $_SESSION['router_pass'] = $pass;
            $_SESSION['connected'] = true;
            
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } catch (Exception $e) {
            $error = 'Connection failed: ' . $e->getMessage();
        }
    }
}

// Check if already connected
$isConnected = isset($_SESSION['connected']) && $_SESSION['connected'] === true;
$host = $_SESSION['router_host'] ?? ROUTER_HOST;
$port = $_SESSION['router_port'] ?? ROUTER_PORT;
$user = $_SESSION['router_user'] ?? '';
$pass = $_SESSION['router_pass'] ?? '';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle AJAX requests
if (isset($_GET['action']) && $isConnected) {
    header('Content-Type: application/json');
    
    try {
        $api = new MikroTikAPI($host, (int)$port, $user, $pass, DEBUG_MODE);
        $api->connect();
        
        switch ($_GET['action']) {
            case 'system':
                $data = $api->query('/system/resource/print');
                echo json_encode(['success' => true, 'data' => $data[0] ?? []]);
                break;
                
            case 'interfaces':
                $data = $api->query('/interface/print');
                echo json_encode(['success' => true, 'data' => $data]);
                break;
                
            case 'firewall':
                $data = $api->query('/ip/firewall/filter/print');
                echo json_encode(['success' => true, 'data' => $data]);
                break;
                
            case 'addresses':
                $data = $api->query('/ip/address/print');
                echo json_encode(['success' => true, 'data' => $data]);
                break;
                
            case 'pppoe':
                $data = $api->query('/interface/pppoe-client/print');
                $activeData = $api->query('/interface/pppoe-server/active/print');
                echo json_encode(['success' => true, 'data' => $data, 'active' => $activeData]);
                break;
                
            case 'cpu':
                $data = $api->query('/system/resource/print');
                echo json_encode(['success' => true, 'data' => $data[0] ?? []]);
                break;
                
            case 'check':
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
        
        $api->disconnect();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikroTik Router Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #1a1a2e; color: #eee; min-height: 100vh; }
        
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.3); }
        .header h1 { font-size: 24px; display: flex; align-items: center; gap: 10px; }
        .header .status { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.1); padding: 8px 16px; border-radius: 20px; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; background: #e74c3c; }
        .status-dot.online { background: #2ecc71; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* Login Form */
        .login-form { max-width: 400px; margin: 100px auto; background: #16213e; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
        .login-form h2 { text-align: center; margin-bottom: 30px; color: #667eea; }
        .login-form .form-group { margin-bottom: 20px; }
        .login-form label { display: block; margin-bottom: 8px; color: #888; }
        .login-form input { width: 100%; padding: 12px 15px; background: #0f0f1a; border: 1px solid #2a3a5a; border-radius: 8px; color: #fff; font-size: 14px; }
        .login-form input:focus { outline: none; border-color: #667eea; }
        .login-form button { width: 100%; padding: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; border-radius: 8px; color: #fff; font-size: 16px; cursor: pointer; transition: transform 0.2s; }
        .login-form button:hover { transform: scale(1.02); }
        
        .error-msg { background: rgba(231, 76, 60, 0.2); color: #e74c3c; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        
        /* Dashboard */
        .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: #16213e; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); }
        .card h3 { color: #667eea; margin-bottom: 15px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
        .card .value { font-size: 32px; font-weight: bold; color: #fff; }
        .card .label { color: #888; font-size: 12px; margin-top: 5px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .stat-box { background: #16213e; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-box .number { font-size: 24px; font-weight: bold; color: #667eea; }
        .stat-box .text { color: #888; font-size: 12px; margin-top: 5px; }
        
        .tabs { display: flex; gap: 5px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab { background: #16213e; padding: 12px 24px; border-radius: 8px 8px 0 0; cursor: pointer; transition: all 0.3s; border: none; color: #888; font-size: 14px; }
        .tab:hover { background: #1f2f50; }
        .tab.active { background: #667eea; color: #fff; }
        
        .content-area { background: #16213e; border-radius: 0 12px 12px 12px; padding: 20px; min-height: 400px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #2a3a5a; }
        th { color: #667eea; font-weight: 600; font-size: 12px; text-transform: uppercase; }
        tr:hover { background: #1f2f50; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .badge-enabled { background: rgba(46, 204, 113, 0.2); color: #2ecc71; }
        .badge-disabled { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }
        .refresh-btn { background: #667eea; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; transition: all 0.3s; }
        .refresh-btn:hover { background: #55a8d3; }
        .loading { text-align: center; padding: 40px; color: #888; }
        .spinner { border: 3px solid #2a3a5a; border-top: 3px solid #667eea; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .section-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .no-data { text-align: center; padding: 40px; color: #888; }
        .logout-btn { background: rgba(231, 76, 60, 0.2); color: #e74c3c; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .logout-btn:hover { background: rgba(231, 76, 60, 0.3); }
        
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .header { flex-direction: column; gap: 15px; } }
    </style>
</head>
<body>
    <?php if (!$isConnected): ?>
        <!-- Login Form -->
        <div class="login-form">
            <h2>🔗 Connect to MikroTik</h2>
            <?php if ($error): ?>
                <div class="error-msg">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Router IP Address</label>
                    <input type="text" name="host" value="<?= htmlspecialchars($host) ?>" placeholder="192.168.1.1" required>
                </div>
                <div class="form-group">
                    <label>Port</label>
                    <input type="number" name="port" value="<?= htmlspecialchars($port) ?>" placeholder="8728" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="admin" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" name="connect">Connect</button>
            </form>
        </div>
    <?php else: ?>
        <!-- Dashboard -->
        <div class="header">
            <h1>📡 MikroTik Dashboard</h1>
            <div style="display: flex; align-items: center; gap: 15px;">
                <div class="status">
                    <div class="status-dot online"></div>
                    <span>Connected to <?= htmlspecialchars($host) ?>:<?= htmlspecialchars($port) ?></span>
                </div>
                <a href="?logout" class="logout-btn">Logout</a>
            </div>
        </div>
        
        <div class="container">
            <div class="cards" id="systemCards">
                <div class="card">
                    <h3>🏠 Router Name</h3>
                    <div class="value" id="routerName">-</div>
                </div>
                <div class="card">
                    <h3>📦 Model</h3>
                    <div class="value" id="model">-</div>
                </div>
                <div class="card">
                    <h3>💻 Architecture</h3>
                    <div class="value" id="arch">-</div>
                </div>
                <div class="card">
                    <h3>📶 Uptime</h3>
                    <div class="value" id="uptime">-</div>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="number" id="cpuLoad">-</div>
                    <div class="text">CPU Load %</div>
                </div>
                <div class="stat-box">
                    <div class="number" id="freeMemory">-</div>
                    <div class="text">Free Memory</div>
                </div>
                <div class="stat-box">
                    <div class="number" id="totalMemory">-</div>
                    <div class="text">Total Memory</div>
                </div>
                <div class="stat-box">
                    <div class="number" id="version">-</div>
                    <div class="text">RouterOS Version</div>
                </div>
            </div>
            
            <div class="tabs">
                <button class="tab active" data-tab="interfaces">🌐 Interfaces</button>
                <button class="tab" data-tab="addresses">🔢 IP Addresses</button>
                <button class="tab" data-tab="firewall">🛡️ Firewall</button>
                <button class="tab" data-tab="pppoe">📡 PPPoE Active</button>
                <button class="tab" data-tab="cpu">💻 CPU Status</button>
            </div>
            
            <div class="content-area">
                <div class="section-title">
                    <h2 id="tabTitle">Network Interfaces</h2>
                    <button class="refresh-btn" onclick="loadTab(currentTab)">🔄 Refresh</button>
                </div>
                <div id="tabContent">
                    <div class="loading"><div class="spinner"></div>Loading...</div>
                </div>
            </div>
        </div>
        
        <script>
            let currentTab = 'interfaces';
            
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    currentTab = tab.dataset.tab;
                    loadTab(currentTab);
                });
            });
            
            function loadTab(tab) {
                const content = document.getElementById('tabContent');
                content.innerHTML = '<div class="loading"><div class="spinner"></div>Loading...</div>';
                
                fetch(`?action=${tab}`)
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            content.innerHTML = `<div class="error-msg">⚠️ ${data.error}</div>`;
                            return;
                        }
                        switch(tab) {
                            case 'interfaces': document.getElementById('tabTitle').textContent = 'Network Interfaces'; renderInterfaces(data.data); break;
                            case 'firewall': document.getElementById('tabTitle').textContent = 'Firewall Rules'; renderFirewall(data.data); break;
                            case 'addresses': document.getElementById('tabTitle').textContent = 'IP Addresses'; renderAddresses(data.data); break;
                            case 'pppoe': document.getElementById('tabTitle').textContent = 'PPPoE Active Clients'; renderPPPoE(data.data, data.active); break;
                            case 'cpu': document.getElementById('tabTitle').textContent = 'CPU Status'; renderCPU(data.data); break;
                        }
                    })
                    .catch(err => { content.innerHTML = `<div class="error-msg">⚠️ Error: ${err}</div>`; });
            }
            
            function loadSystemInfo() {
                fetch('?action=system')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            const d = data.data;
                            document.getElementById('routerName').textContent = d['name'] || '-';
                            document.getElementById('model').textContent = d['model'] || '-';
                            document.getElementById('arch').textContent = d['architecture-name'] || '-';
                            document.getElementById('uptime').textContent = formatUptime(d['uptime'] || '');
                            document.getElementById('cpuLoad').textContent = (d['cpu-load'] || '0') + '%';
                            document.getElementById('freeMemory').textContent = formatBytes(d['free-memory'] || 0);
                            document.getElementById('totalMemory').textContent = formatBytes(d['total-memory'] || 0);
                            document.getElementById('version').textContent = d['version'] || '-';
                        }
                    })
                    .catch(() => { document.getElementById('routerName').textContent = 'Offline'; });
            }
            
            function renderInterfaces(data) {
                if (!data.length) { document.getElementById('tabContent').innerHTML = '<div class="no-data">No interfaces found</div>'; return; }
                let html = '<table><thead><tr><th>Name</th><th>Type</th><th>Status</th><th>RX</th><th>TX</th></tr></thead><tbody>';
                data.forEach(i => { html += `<tr><td><strong>${i.name || '-'}</strong></td><td>${i.type || '-'}</td><td><span class="badge ${i.disabled === 'true' ? 'badge-disabled' : 'badge-enabled'}">${i.disabled === 'true' ? 'Disabled' : 'Enabled'}</span></td><td>${formatBytes(i['rx-byte'] || 0)}</td><td>${formatBytes(i['tx-byte'] || 0)}</td></tr>`; });
                html += '</tbody></table>';
                document.getElementById('tabContent').innerHTML = html;
            }
            
            function renderPPPoE(data, activeData) {
                if (!data.length) { document.getElementById('tabContent').innerHTML = '<div class="no-data">No PPPoE clients found</div>'; return; }
                let html = '<table><thead><tr><th>Name</th><th>Interface</th><th>Status</th><th>User</th><th>IP Address</th><th>Uptime</th></tr></thead><tbody>';
                data.forEach(p => { 
                    const isRunning = p['running'] === 'true';
                    html += `<tr><td><strong>${p.name || '-'}</strong></td><td>${p.interface || '-'}</td><td><span class="badge ${isRunning ? 'badge-enabled' : 'badge-disabled'}">${isRunning ? 'Connected' : 'Disconnected'}</span></td><td>${p.user || '-'}</td><td>${p['ip-address'] || '-'}</td><td>${p['uptime'] || '-'}</td></tr>`; 
                });
                html += '</tbody></table>';
                
                if (activeData && activeData.length) {
                    html += '<h3 style="margin-top: 20px; color: #667eea;">Active PPPoE Sessions</h3>';
                    html += '<table><thead><tr><th>User</th><th>IP Address</th><th>MAC Address</th><th>Session ID</th><th>Uptime</th></tr></thead><tbody>';
                    activeData.forEach(a => {
                        html += `<tr><td><strong>${a.user || '-'}</strong></td><td>${a['ip-address'] || '-'}</td><td>${a['mac-address'] || '-'}</td><td>${a['session-id'] || '-'}</td><td>${a['uptime'] || '-'}</td></tr>`;
                    });
                    html += '</tbody></table>';
                }
                
                document.getElementById('tabContent').innerHTML = html;
            }
            
            function renderFirewall(data) {
                if (!data.length) { document.getElementById('tabContent').innerHTML = '<div class="no-data">No firewall rules found</div>'; return; }
                let html = '<table><thead><tr><th>#</th><th>Chain</th><th>Action</th><th>Src Address</th><th>Dst Address</th><th>Protocol</th><th>Disabled</th></tr></thead><tbody>';
                data.forEach((f, i) => { html += `<tr><td>${i + 1}</td><td>${f.chain || '-'}</td><td><strong>${f.action || '-'}</strong></td><td>${f['src-address'] || '-'}</td><td>${f['dst-address'] || '-'}</td><td>${f.protocol || '-'}</td><td><span class="badge ${f.disabled === 'true' ? 'badge-disabled' : 'badge-enabled'}">${f.disabled === 'true' ? 'Yes' : 'No'}</span></td></tr>`; });
                html += '</tbody></table>';
                document.getElementById('tabContent').innerHTML = html;
            }
            
            function renderAddresses(data) {
                if (!data.length) { document.getElementById('tabContent').innerHTML = '<div class="no-data">No IP addresses found</div>'; return; }
                let html = '<table><thead><tr><th>Address</th><th>Network</th><th>Interface</th><th>Disabled</th></tr></thead><tbody>';
                data.forEach(a => { html += `<tr><td><strong>${a.address || '-'}</strong></td><td>${a.network || '-'}</td><td>${a.interface || '-'}</td><td><span class="badge ${a.disabled === 'true' ? 'badge-disabled' : 'badge-enabled'}">${a.disabled === 'true' ? 'Yes' : 'No'}</span></td></tr>`; });
                html += '</tbody></table>';
                document.getElementById('tabContent').innerHTML = html;
            }
            
            function renderCPU(data) {
                if (!data || Object.keys(data).length === 0) { document.getElementById('tabContent').innerHTML = '<div class="no-data">No CPU data available</div>'; return; }
                let html = '<table><thead><tr><th>Property</th><th>Value</th></tr></thead><tbody>';
                html += `<tr><td><strong>CPU Load</strong></td><td>${data['cpu-load'] || '-'}%</td></tr>`;
                html += `<tr><td><strong>CPU Count</strong></td><td>${data['cpu-count'] || '-'}</td></tr>`;
                html += `<tr><td><strong>Free Memory</strong></td><td>${formatBytes(data['free-memory'] || 0)}</td></tr>`;
                html += `<tr><td><strong>Total Memory</strong></td><td>${formatBytes(data['total-memory'] || 0)}</td></tr>`;
                html += `<tr><td><strong>Free HDD Space</strong></td><td>${formatBytes(data['free-hdd-space'] || 0)}</td></tr>`;
                html += `<tr><td><strong>Total HDD Space</strong></td><td>${formatBytes(data['total-hdd-space'] || 0)}</td></tr>`;
                html += `<tr><td><strong>Uptime</strong></td><td>${formatUptime(data['uptime'] || '')}</td></tr>`;
                html += `<tr><td><strong>Board Name</strong></td><td>${data['board-name'] || '-'}</td></tr>`;
                html += `<tr><td><strong>Model</strong></td><td>${data['model'] || '-'}</td></tr>`;
                html += `<tr><td><strong>Version</strong></td><td>${data['version'] || '-'}</td></tr>`;
                html += `<tr><td><strong>Architecture</strong></td><td>${data['architecture-name'] || '-'}</td></tr>`;
                html += '</tbody></table>';
                document.getElementById('tabContent').innerHTML = html;
            }
            
            function formatBytes(bytes) {
                bytes = parseInt(bytes) || 0;
                if (bytes >= 10737418240) return (bytes / 10737418240).toFixed(2) + ' GB';
                if (bytes >= 10485760) return (bytes / 10485760).toFixed(2) + ' MB';
                if (bytes >= 10240) return (bytes / 10240).toFixed(2) + ' KB';
                return bytes + ' B';
            }
            
            function formatUptime(uptime) {
                if (!uptime) return '-';
                const regex = /(\d+)w|(\d+)d|(\d+)h|(\d+)m|(\d+)s/gi;
                let match, result = [];
                while ((match = regex.exec(uptime)) !== null) {
                    if (match[1]) result.push(match[1] + 'w');
                    if (match[2]) result.push(match[2] + 'd');
                    if (match[3]) result.push(match[3] + 'h');
                    if (match[4]) result.push(match[4] + 'm');
                }
                return result.slice(0, 4).join(' ') || uptime;
            }
            
            loadSystemInfo();
            loadTab('interfaces');
            setInterval(() => { loadSystemInfo(); loadTab(currentTab); }, 30000);
        </script>
    <?php endif; ?>
</body>
</html>
