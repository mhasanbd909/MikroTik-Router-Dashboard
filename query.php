<?php
/**
 * MikroTik Quick Query Tool
 * 
 * Run individual RouterOS commands and see raw output
 */

session_start();
require_once 'MikroTikAPI.php';

$command = $_POST['command'] ?? '';
$output = [];
$error = '';

// Check if connected
$isConnected = isset($_SESSION['connected']) && $_SESSION['connected'] === true;
$host = $_SESSION['router_host'] ?? ROUTER_HOST;
$port = $_SESSION['router_port'] ?? ROUTER_PORT;
$user = $_SESSION['router_user'] ?? '';
$pass = $_SESSION['router_pass'] ?? '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if (!$isConnected) {
    header('Location: index.php');
    exit;
}

if ($command) {
    try {
        $api = new MikroTikAPI($host, $port, $user, $pass, DEBUG_MODE);
        $api->connect();
        
        $lines = explode("\n", trim($command));
        $cmdPath = trim($lines[0]);
        $params = [];
        
        for ($i = 1; $i < count($lines); $i++) {
            if (preg_match('/^(\w+)=(.+)$/', trim($lines[$i]), $m)) {
                $params[$m[1]] = $m[2];
            }
        }
        
        $rawResponse = $api->sendCommand($cmdPath, $params);
        $parsedData = $api->parseResponse($rawResponse);
        
        $output = [
            'command' => $cmdPath,
            'raw' => $rawResponse,
            'parsed' => $parsedData
        ];
        
        $api->disconnect();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Predefined commands
$presets = [
    '/system/resource/print',
    '/interface/print',
    '/ip/address/print',
    '/ip/dhcp-server/lease/print',
    '/ip/firewall/filter/print',
    '/ip/firewall/nat/print',
    '/ip/hotspot/active/print',
    '/system/health/print',
    '/system/log/print',
    '/queue/simple/print'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikroTik Query Tool</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Consolas', monospace; background: #1a1a2e; color: #eee; padding: 20px; }
        h1 { margin-bottom: 20px; color: #667eea; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #888; }
        textarea { width: 100%; height: 100px; background: #16213e; border: 1px solid #2a3a5a; color: #fff; padding: 10px; border-radius: 8px; font-family: inherit; }
        button { background: #667eea; color: #fff; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 14px; }
        button:hover { background: #5568d3; }
        .presets { margin-bottom: 20px; }
        .preset-btn { background: #16213e; color: #888; padding: 6px 12px; margin: 3px; border-radius: 6px; cursor: pointer; font-size: 12px; border: 1px solid #2a3a5a; }
        .preset-btn:hover { background: #1f2f50; color: #fff; }
        .output { background: #0f0f1a; padding: 15px; border-radius: 8px; margin-top: 20px; overflow-x: auto; }
        .output h3 { color: #667eea; margin-bottom: 10px; }
        pre { white-space: pre-wrap; font-size: 13px; }
        .error { color: #e74c3c; margin-bottom: 15px; }
        .section { margin-bottom: 30px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .back-btn { background: #16213e; color: #888; padding: 10px 20px; border-radius: 8px; text-decoration: none; }
        .back-btn:hover { background: #1f2f50; color: #fff; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔧 MikroTik Query Tool</h1>
        <div>
            <a href="query.php?logout" class="back-btn" style="margin-right: 10px;">Logout</a>
            <a href="index.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="error">Error: <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="section">
        <div class="presets">
            <label>Quick Commands:</label>
            <?php foreach ($presets as $p): ?>
                <button class="preset-btn" onclick="setCommand('<?= htmlspecialchars($p) ?>')"><?= $p ?></button>
            <?php endforeach; ?>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Command (path on first line, params on following lines):</label>
                <textarea name="command" placeholder="/system/resource/print
name=value"><?= htmlspecialchars($command) ?></textarea>
            </div>
            <button type="submit">Execute Command</button>
        </form>
    </div>
    
    <?php if (!empty($output)): ?>
        <div class="output">
            <h3>Command: <?= htmlspecialchars($output['command']) ?></h3>
            <h3 style="margin-top: 20px;">Raw Response:</h3>
            <pre><?= htmlspecialchars(implode("\n", $output['raw'])) ?></pre>
            <h3 style="margin-top: 20px;">Parsed Data (<?= count($output['parsed']) ?> records):</h3>
            <pre><?= htmlspecialchars(json_encode($output['parsed'], JSON_PRETTY_PRINT)) ?></pre>
        </div>
    <?php endif; ?>
    
    <script>
        function setCommand(cmd) {
            document.querySelector('textarea[name="command"]').value = cmd;
        }
    </script>
</body>
</html>
