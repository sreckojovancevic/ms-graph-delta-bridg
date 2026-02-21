<?php
declare(strict_types=1);

// Podesavamo putanje da gledaju u src folder
require_once __DIR__ . '/../src/DriveModuleDelta.php';
require_once __DIR__ . '/../src/ExchangeModuleDelta.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$user = $_GET['user'] ?? $_GET['email'] ?? 'me';

// Placeholderi za tvoje objekte - kasnije cemo ovde ucitati config.php
$storage = new stdClass(); 
$graph = new stdClass(); 

try {
    switch (true) {
        // OneDrive sinhronizacija
        case preg_match('#/v1\.0/users/[^/]+/drive/root/delta#', $path):
            DriveModuleDelta::syncDelta($graph, $storage, $user, 'me', 'files');
            echo json_encode([
                "@odata.context" => "https://graph.microsoft.com/v1.0/$path",
                "status" => "completed", 
                "module" => "onedrive",
                "system_stats" => [
                    "memory" => round(memory_get_usage(true) / 1024 / 1024, 2) . " MB",
                    "processes" => (int)shell_exec("ps -ax | wc -l")
                ]
            ]);
            break;
            
        // Exchange/Email sinhronizacija
        case preg_match('#/v1\.0/users/[^/]+/mailFolders/[^/]+/messages/delta#', $path):
            ExchangeModuleDelta::syncDelta($graph, $storage, $user);
            echo json_encode([
                "@odata.context" => "https://graph.microsoft.com/v1.0/$path",
                "status" => "completed", 
                "module" => "exchange+ews"
            ]);
            break;

        // Tvoj Admin Panel monitoring
        case ($path === '/v1.0/admin/status'):
            echo json_encode([
                "bridge_status" => "online",
                "active_processes" => (int)shell_exec("ps -ax | wc -l"),
                "ram_usage" => round(memory_get_usage(true) / 1024 / 1024, 2) . " MB",
                "uptime" => shell_exec('uptime -p')
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(["error" => "Endpoint not found on Bridge"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
