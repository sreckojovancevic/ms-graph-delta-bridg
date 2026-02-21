<?php
declare(strict_types=1);

// PSR-4 autoloading bi bio idealan ovde, ali za pocetak:
require_once __DIR__ . '/../src/DriveModuleDelta.php';
require_once __DIR__ . '/../src/ExchangeModuleDelta.php';
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$user = $_GET['user'] ?? 'me';

// Helper za sistemske resurse (tvoj admin panel zahtev)
function getSystemStats(): array {
    return [
        'processes' => (int)shell_exec("ps -ax | wc -l"),
        'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        'load' => sys_getloadavg()[0] ?? 0
    ];
}

try {
    switch (true) {
        // OneDrive Delta Endpoint
        case preg_match('#/v1\.0/users/[^/]+/drive/root/delta#', $path):
            $result = DriveModuleDelta::syncDelta($graph, $storage, $user);
            echo json_encode([
                "status" => "success",
                "module" => "onedrive",
                "system" => getSystemStats(), // Bonus info za admina
                "data" => $result
            ]);
            break;

        // Monitoring endpoint (Admin Panel)
        case ($path === '/v1.0/admin/status'):
            echo json_encode([
                "bridge_version" => "1.0.0-beta",
                "system_health" => getSystemStats(),
                "uptime" => shell_exec('uptime -p')
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode(["error" => "Endpoint not mapped in Bridge"]);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}
