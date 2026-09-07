<?php
/**
 * Simple 1-Click Git Deploy Script for cPanel
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

chdir(__DIR__ . '/..');

$output = [];
exec('git fetch origin main 2>&1', $output);
exec('git reset --hard origin/main 2>&1', $output);

header('Content-Type: text/html');
?>
<!DOCTYPE html>
<html>
<head>
    <title>cPanel Auto Deploy</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #38bdf8; padding: 20px; }
        .card { background: #1e293b; padding: 20px; border-radius: 12px; border: 1px solid #334155; }
        h2 { color: #4ade80; margin-top: 0; }
        pre { background: #090d16; padding: 15px; border-radius: 8px; color: #f8fafc; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🚀 CareMate AI Backend Deployment</h2>
        <p>Current Time: <?php echo date('Y-m-d H:i:s'); ?></p>
        <h3>Command Execution Output:</h3>
        <pre><?php echo implode("\n", $output); ?></pre>
    </div>
</body>
</html>
