<?php

namespace App\Jobs;

use App\Models\CloudConnectSetting;
use App\Services\CloudConnectService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class CloudConnectHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(CloudConnectService $cloudConnectService, SettingsService $settingsService): void
    {
        // Check if cloud connect is enabled
        $settings = CloudConnectSetting::getInstance();
        if (!$settings->enabled) {
            Log::debug('Cloud Connect heartbeat skipped - not enabled');
            return;
        }

        // Check if we have an instance token
        $status = $cloudConnectService->getStatus();
        if (!$status['has_instance']) {
            Log::debug('Cloud Connect heartbeat skipped - no instance configured');
            return;
        }

        // Check if tunnel is supposed to be connected
        if ($status['status'] !== 'connected' && $status['status'] !== 'reconnecting') {
            Log::debug('Cloud Connect heartbeat skipped - tunnel not connected');
            return;
        }

        try {
            // Check actual tunnel status
            if (!$status['tunnel_active']) {
                Log::warning('Cloud Connect tunnel not active, attempting to reconnect');
                $this->attemptReconnect($cloudConnectService);
                return;
            }

            // Send heartbeat
            $result = $cloudConnectService->sendHeartbeat();
            Log::debug('Cloud Connect heartbeat sent successfully', $result);

        } catch (Exception $e) {
            Log::error('Cloud Connect heartbeat failed: ' . $e->getMessage());
            
            // If heartbeat fails, try to reconnect
            $this->attemptReconnect($cloudConnectService);
        }
    }

    /**
     * Attempt to reconnect the tunnel
     * First tries a simple reconnect, then falls back to auto-reconnect by GUID if needed
     */
    protected function attemptReconnect(CloudConnectService $cloudConnectService): void
    {
        try {
            Log::info('Cloud Connect attempting to reconnect tunnel');
            $cloudConnectService->connect();
            Log::info('Cloud Connect tunnel reconnected successfully');
        } catch (Exception $e) {
            Log::warning('Cloud Connect simple reconnection failed: ' . $e->getMessage());
            
            // Try auto-reconnect which can find and re-link instance by GUID
            Log::info('Cloud Connect attempting auto-reconnect by Erugo GUID');
            if ($cloudConnectService->attemptAutoReconnect()) {
                Log::info('Cloud Connect auto-reconnect successful');
            } else {
                Log::error('Cloud Connect auto-reconnect also failed');
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('Cloud Connect heartbeat job failed permanently: ' . ($exception?->getMessage() ?? 'Unknown error'));
    }
}
