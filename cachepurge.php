<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;

class CachePurgePlugin extends Plugin
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    public function onPluginsInitialized(): void
    {
        $this->enable([
            'onAfterCacheClear' => ['purgeAll', 0]
        ]);
    }

    public function purgeAll(): void
    {
        $this->log('=== CACHE PURGE START ===');

        try {
            $this->purgeMicrocache();
        } catch (\Throwable $e) {
            $this->log('Microcache ERROR: ' . $e->getMessage());
        }

        try {
            $this->purgeCloudflare();
        } catch (\Throwable $e) {
            $this->log('Cloudflare ERROR: ' . $e->getMessage());
        }

        $this->log('=== CACHE PURGE END ===');
    }

    /**
     * Purge microcache nginx
     */
    private function purgeMicrocache(): void
    {
        $target = '/dev/shm/microcache';
        $real   = realpath($target);

        if ($real === false) {
            $this->log("Microcache: path not found ($target)");
            return;
        }

        if ($real !== $target) {
            $this->log("Microcache: path traversal détecté ($real != $target) — ABORT");
            return;
        }

        $cmd = 'find ' . escapeshellarg($real) . ' -mindepth 1 -delete 2>&1';
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->log('Microcache: find failed (exit ' . $exitCode . ') → ' . implode(' | ', $output));
            return;
        }

        $this->log('Microcache: purged successfully');
    }

    /**
     * Purge Cloudflare via API
     */
    private function purgeCloudflare(): void
    {
        $this->log('Cloudflare: START');

        $zoneId   = $this->config->get('plugins.cachepurge.zone_id');
        $apiToken = $this->config->get('plugins.cachepurge.api_token');

        if (empty($zoneId) || empty($apiToken)) {
            $this->log('Cloudflare: config missing (zone_id or api_token)');
            return;
        }

        if (!function_exists('curl_init')) {
            $this->log('Cloudflare: cURL not available');
            return;
        }

        $url     = "https://api.cloudflare.com/client/v4/zones/$zoneId/purge_cache";
        $payload = json_encode(['purge_everything' => true]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $apiToken",
                "Content-Type: application/json",
            ],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $this->log('Cloudflare: cURL error → ' . curl_error($ch));
            curl_close($ch);
            return;
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->log("Cloudflare: HTTP $httpCode");

        $decoded = json_decode($response, true);

        if ($httpCode !== 200 || !($decoded['success'] ?? false)) {
            $errorMsg = $decoded['errors'][0]['message'] ?? 'unknown error';
            $this->log("Cloudflare: FAILED → $errorMsg");
            return;
        }

        $this->log('Cloudflare: SUCCESS');
    }

    /**
     * Logger thread-safe vers /var/log/grav/cachepurge.log
     */
    private function log(string $message): void
    {
        $logFile = '/var/log/grav/cachepurge.log';

        if (!is_dir('/var/log/grav')) {
            mkdir('/var/log/grav', 0750, true);
        }

        file_put_contents(
            $logFile,
            date('c') . ' ' . $message . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
