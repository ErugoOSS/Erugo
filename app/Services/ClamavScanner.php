<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClamavScanner
{
    /**
     * Scan a file that lives under storage/app using the ClamAV REST API.
     *
     * @param  string  $storagePath  Path relative to storage/app
     *                               e.g. "temp/1/uuid.ext"
     * @return bool                  true = clean (or skipped), false = infected or error
     */
    public function scanPath(string $storagePath): bool
    {
        // CLAMAV_URL is set via docker-compose environment
        $url = env('CLAMAV_URL');

        if (!$url) {
            Log::warning('ClamAV URL not configured, skipping AV scan.', [
                'storage_path' => $storagePath,
            ]);

            // Allow upload if AV not configured
            return true;
        }

        // IMPORTANT: file is under storage/app/<storagePath>
        // We don't use Storage::path() because Erugo's default disk points to storage/app/private
        $absolutePath = storage_path('app/' . ltrim($storagePath, '/'));

        if (!is_readable($absolutePath)) {
            Log::error('ClamAV scan: file not readable.', [
                'absolute_path' => $absolutePath,
            ]);

            return false;
        }

        $handle = @fopen($absolutePath, 'r');
        if ($handle === false) {
            Log::error('ClamAV scan: fopen failed.', [
                'absolute_path' => $absolutePath,
            ]);

            return false;
        }

        try {
            // Optional: log that we're about to scan
            Log::info('ClamAV scan: sending file to scanner.', [
                'absolute_path' => $absolutePath,
                'url'           => $url,
            ]);

            $response = Http::attach(
                'FILES',
                $handle,
                basename($absolutePath)
            )->post($url);

            if (!$response->successful()) {
                Log::error('ClamAV scan HTTP error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);

                return false;
            }

            $json    = $response->json();
            $results = $json['data']['result'] ?? [];

            foreach ($results as $fileResult) {
                if (!empty($fileResult['is_infected'])) {
                    Log::warning('ClamAV detected malware', [
                        'file'    => $fileResult['name'] ?? basename($absolutePath),
                        'viruses' => $fileResult['viruses'] ?? [],
                    ]);

                    return false;
                }
            }

            // Clean
            return true;
        } catch (\Throwable $e) {
            Log::error('ClamAV scan exception', [
                'error' => $e->getMessage(),
                'path'  => $absolutePath,
            ]);

            return false;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}

