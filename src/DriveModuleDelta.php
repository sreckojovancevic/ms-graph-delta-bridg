
cat DriveModuleDelta.php
<?php
declare(strict_types=1);

/**
 * DriveModuleDelta v2.3.4 – PRODUCTION READY
 * ✅ Microsoft Graph PHP SDK v2 (Kiota)
 * ✅ FIX: deltaPageFromUrl() sa RequestInformation
 * ✅ FIX: getFolder() Fatal Error protection
 * ✅ Integrisan sa Indexer v2.5 (SHA-256 + Meta)
 */

use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Abstractions\RequestInformation;
use Microsoft\Graph\Generated\Models\DriveItemCollectionResponse;
use Psr\Http\Message\StreamInterface;

if (!function_exists('logical_version_id')) {
    function logical_version_id($item): string {
        // Sigurna provera fajla (da izbegnemo undefined method getFile)
        $file = method_exists($item, 'getFile') ? $item->getFile() : null;
        $hashes = $file ? $file->getHashes() : null;

        if ($hashes) {
            $sha256 = $hashes->getSha256Hash();
            if (!empty($sha256)) return $sha256;
            $quickXor = $hashes->getQuickXorHash();
            if (!empty($quickXor)) return $quickXor;
        }

        $etag = method_exists($item, 'getETag') ? $item->getETag() : null;
        if (!empty($etag)) return $etag;

        $msId = method_exists($item, 'getId') ? $item->getId() : 'unknown';
        $size = method_exists($item, 'getSize') ? (int)$item->getSize() : 0;
        return "{$msId}:{$size}";
    }
}

if (!function_exists('stream_to_resource')) {
    function stream_to_resource(StreamInterface $stream) {
        $res = $stream->detach();
        if (is_resource($res)) return $res;
        $tmp = fopen('php://temp', 'w+');
        while (!$stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') break;
            fwrite($tmp, $chunk);
        }
        rewind($tmp);
        return $tmp;
    }
}

if (!function_exists('humanLikeSleep')) {
    function humanLikeSleep(): void {
        usleep(mt_rand(800, 1500) * 1000);
    }
}

final class DriveModuleDelta
{
    public static function syncDelta(
        GraphServiceClient $graph,
        $storage,
        string $entityName,
        string $driveId,
        string $type
    ): void {
        Logger::log("Započinjem DELTA $type sync za: $entityName");

        $paths = $storage->prepareEntityStorage($type, $entityName);
        $indexer = new Indexer($paths['db'], $type, $paths['entity_id'], $entityName);
        $dl = new DownloaderSHA256($storage);

        $tokens = new SimpleDeltaTokenStorage($paths['db'], $entityName);
        $stats = [
            'pages' => 0, 'total_files' => 0, 'total_folders' => 0,
            'total_skipped' => 0, 'successful' => 0, 'failed' => 0, 'deletions' => 0
        ];

        $scopeKey = "drive_{$type}:" . substr($driveId, 0, 8);

        try {
            if ($driveId === 'me') {
                $drive = $graph->users()->byUserId($entityName)->drive()->get()->wait();
                $driveId = $drive->getId();
                humanLikeSleep();
            }

            $deltaUrl = $tokens->getToken($scopeKey);

            if (!$deltaUrl) {
                Logger::log("DELTA: Initial sync (no token) za $driveId", "INFO");
                $page = self::initialDeltaPage($graph, $driveId);
            } else {
                Logger::log("DELTA: Nastavljam preko sačuvanog tokena", "INFO");
                $page = self::deltaPageFromUrl($graph, $deltaUrl);
            }

            if ($page === null && $deltaUrl) {
                Logger::log("DELTA: Token istekao/nevažeći → Resetujem.", "WARN");
                $tokens->clearToken($scopeKey);
                $page = self::initialDeltaPage($graph, $driveId);
            }

            while ($page) {
                $stats['pages']++;
                $items = method_exists($page, 'getValue') ? $page->getValue() : [];

                foreach ($items as $item) {
                    $msId = $item->getId();

                    if (method_exists($item, 'getDeleted') && $item->getDeleted()) {
                        $stats['deletions']++;
                        continue;
                    }

                    // --- SIGURNA PROVERA FOLDERA (DA NE PUKNE KAO PROŠLI PUT) ---
                    $isFolder = false;
                    if (method_exists($item, 'getFolder') && $item->getFolder() !== null) {
                        $isFolder = true;
                    } elseif (method_exists($item, 'getAdditionalData')) {
                        $add = $item->getAdditionalData();
                        if (isset($add['folder'])) $isFolder = true;
                    }

                    if ($isFolder) {
                        $stats['total_folders']++;
                        continue;
                    }
                    // -----------------------------------------------------------

                    $stats['total_files']++;
                    $name = $item->getName() ?? 'unnamed';
                    $size = (int)($item->getSize() ?? 0);
                    $lastMod = $item->getLastModifiedDateTime();
                    $logicalId = logical_version_id($item);

                    if (!$indexer->shouldUpdate($msId, $logicalId)) {
                        $stats['total_skipped']++;
                        continue;
                    }

                    $saved = ['ok' => false, 'sha256' => null];
                    $res = null;

                    try {
                        humanLikeSleep();
                        $contentStream = $graph->drives()
                            ->byDriveId($driveId)
                            ->items()
                            ->byDriveItemId($msId)
                            ->content()
                            ->get()
                            ->wait();

                        $res = ($contentStream instanceof StreamInterface)
                            ? stream_to_resource($contentStream)
                            : $contentStream;

                        if (is_resource($res)) {
                            $saved = $dl->saveStreamComputeSha($paths['files'], $res, $type);
                            fclose($res);
                            $res = null;
                        }

                        if ($saved['ok']) {
                            $indexer->logVersion([
                                'ms_id' => $msId,
                                'hash'  => $saved['sha256'] ?? $logicalId,
                                'name'  => $name,
                                'path'  => $item->getParentReference()?->getPath() ?? '/',
                                'size'  => $size,
                                'backup_at' => $lastMod ? $lastMod->format('Y-m-d H:i:s') : date('Y-m-d H:i:s')
                            ]);
                            $stats['successful']++;
                        } else {
                            $stats['failed']++;
                        }
                    } catch (\Throwable $e) {
                        Logger::log("DL Error [$name]: " . $e->getMessage(), "ERROR");
                        $stats['failed']++;
                    } finally {
                        if (is_resource($res)) fclose($res);
                    }
                }

                $nextLink = method_exists($page, 'getOdataNextLink') ? $page->getOdataNextLink() : null;
                $deltaLink = method_exists($page, 'getOdataDeltaLink') ? $page->getOdataDeltaLink() : null;

                if ($nextLink) {
                    $page = self::deltaPageFromUrl($graph, $nextLink);
                } elseif ($deltaLink) {
                    $tokens->saveToken($scopeKey, $deltaLink);
                    $page = null;
                } else {
                    $page = null;
                }
            }

            Logger::log("DELTA FINISHED: Files={$stats['total_files']}, New={$stats['successful']}, Skipped={$stats['total_skipped']}");

        } catch (\Throwable $e) {
            Logger::log("DriveModuleDelta FATAL: " . $e->getMessage(), "ERROR");
        }
    }

    private static function initialDeltaPage(GraphServiceClient $graph, string $driveId) {
        try {
            return $graph->drives()
                ->byDriveId($driveId)
                ->items()
                ->byDriveItemId('root')
                ->delta()
                ->get()
                ->wait();
        } catch (\Throwable $e) {
            Logger::log("Initial Delta Error: " . $e->getMessage(), "ERROR");
            return null;
        }
    }

    private static function deltaPageFromUrl(GraphServiceClient $graph, string $url) {
        try {
            $requestAdapter = $graph->getRequestAdapter();
            $requestInfo = new RequestInformation();
            $requestInfo->httpMethod = 'GET';
            $requestInfo->setUri($url);

            return $requestAdapter->sendAsync(
                $requestInfo,
                [DriveItemCollectionResponse::class, 'createFromDiscriminatorValue']
            )->wait();
        } catch (\Throwable $e) {
            Logger::log("Delta URL Page Error: " . $e->getMessage(), "ERROR");
            return null;
        }
    }
}
