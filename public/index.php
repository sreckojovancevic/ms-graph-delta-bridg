<?php
declare(strict_types=1);

// Povezivanje sa "motorima" u src folderu
require_once __DIR__ . '/../src/DriveModuleDelta.php';
require_once __DIR__ . '/../src/ExchangeModuleDelta.php';

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$user = $_GET['user'] ?? $_GET['email'] ?? 'me';

// Placeholderi za sistemske objekte
$storage = new stdClass(); 
$graph = new stdClass(); 

try {
    switch (true) {
        // RUTA 1: OneDrive
        case preg_match('#/v1\.0/users/[^/]+/drive/root/delta#', $path):
            $result = DriveModuleDelta::syncDelta($graph, $storage, $user);
            echo json_encode([
                "status" => "completed", 
                "module" => "onedrive",
                "data" => $result
            ]);
            break;
            
        // RUTA 2: Exchange
        case preg_match('#/v1\.0/users/[^/]+/mailFolders/[^/]+/messages/delta#', $path):
            $result = ExchangeModuleDelta::syncDelta($graph, $storage, $user);
            echo json_encode([
                "status" => "completed", 
                "module" => "exchange",
                "data" => $result
            ]);
            break;

        // RUTA 3: Tvoj Admin Panel (Monitoring)
        case ($path === '/v1.0/admin/status'):
            echo json_encode([
                "bridge_status" => "online",
                "active_processes" => (int)shell_exec("ps -ax | wc -l"),
                "ram_usage" => round(memory_get_usage(true) / 1024 / 1024, 2) . " MB",
                "cpu_load" => sys_getloadavg()[0]
            ]);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(["error" => "Endpoint not found"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
