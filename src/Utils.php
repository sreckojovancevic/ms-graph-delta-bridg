<?php
declare(strict_types=1);

class BridgeLogger {
    public static function log(string $message, string $level = 'INFO'): void {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) { mkdir($logDir, 0777, true); }
        
        $file = $logDir . '/bridge_' . date('Y-m-d') . '.log';
        $entry = sprintf("[%s] [%s]: %s\n", date('H:i:s'), $level, $message);
        file_put_contents($file, $entry, FILE_APPEND);
    }
}
