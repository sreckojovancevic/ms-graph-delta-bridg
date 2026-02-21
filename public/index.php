<?php
declare(strict_types=1);

/**
 * MS-Graph-Delta-Bridge
 * Lead Architect: Srećko Jovančević
 * Collaborative Partner: Gemini AI
 */

// 1. Povezivanje sa modulima i alatima
require_once __DIR__ . '/../src/DriveModuleDelta.php';
require_once __DIR__ . '/../src/ExchangeModuleDelta.php';
require_once __DIR__ . '/../src/Utils.php'; // Logger i pomoćne funkcije

// 2. Osnovna zaglavlja
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$user = $_GET['user'] ?? $_GET['email'] ?? 'me';

// Placeholderi za sistemske objekte (kasnije se pune iz config.php)
$storage = new stdClass(); 
$graph = new stdClass(); 

try {
    switch (true) {
        // --- OneDrive Delta Sync ---
        case preg_match('#/v1\.0/users/[^/]+/drive/root/delta#', $path):
            BridgeLogger::log("OneDrive sync initiated for: $user");
            $result = DriveModuleDelta::syncDelta($graph, $storage, $user);
            echo json_encode([
                "status" => "completed", 
                "module" => "onedrive",
                "data" => $result
            ]);
            break;
            
        // --- Exchange/Mail Delta Sync ---
        case preg_match('#/v1\.0/users/[^/]+/mailFolders/[^/]+/messages/delta#', $path):
            BridgeLogger::log("Exchange sync initiated for: $user");
            $result = ExchangeModuleDelta::syncDelta($graph, $storage, $user);
            echo json_encode([
                "status" => "completed", 
                "module" => "exchange",
                "data" => $result
            ]);
            break;

        // --- Admin Panel & System Health (Tvoj monitoring) ---
        case ($path === '/v1.0/admin/status'):
            $currentStatus = [
                "timestamp" => date('Y-m-d H:i:s'),
                "active_processes" => (int)shell_exec("ps -ax | wc -l"),
                "ram_usage_mb" => round(memory_get_usage(true) / 1024 / 1024, 2),
                "cpu_load" => sys_getloadavg()[0]
            ];

            // Čuvanje istorije u JSON fajlu (za grafikone)
            $historyFile = __DIR__ . '/../logs/history.json';
            $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : [];
            array_unshift($history, $currentStatus);
            $history = array_slice($history, 0, 15); // Čuvamo zadnjih 15 merenja
            
            if (!is_dir(__DIR__ . '/../logs')) { mkdir(__DIR__ . '/../logs', 0777, true); }
            file_put_contents($historyFile, json_encode($history));

            echo json_encode([
                "bridge_status" => "online",
                "current" => $currentStatus,
                "history" => $history,
                "uptime" => shell_exec('uptime -p')
            ]);
            break;
            
        // --- Default 404 ---
        default:
            http_response_code(404);
            echo json_encode(["error" => "Endpoint not found on Bridge"]);
    }
} catch (Exception $e) {
    BridgeLogger::log("Fatal Error: " . $e->getMessage(), "ERROR");
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
