<?php

namespace App\Console\Commands;

use App\Models\CloudConnectSetting;
use App\Services\CloudConnectService;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Exception;

class CloudConnectAutoReconnect extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cloud-connect:auto-reconnect';

    /**
     * The console command description.
     */
    protected $description = 'Attempt to automatically reconnect to Cloud Connect tunnel after container restart';

    /**
     * Execute the console command.
     */
    public function handle(CloudConnectService $cloudConnectService, SettingsService $settingsService): int
    {
        // Check if cloud connect was previously enabled
        $settings = CloudConnectSetting::getInstance();
        if (!$settings->enabled) {
            $this->info('Cloud Connect is not enabled, skipping auto-reconnect.');
            return Command::SUCCESS;
        }

        $this->info('Cloud Connect was previously enabled, attempting auto-reconnect...');

        try {
            // First, check if we have the required capabilities
            $capabilities = $cloudConnectService->checkCapabilities();
            if (!$capabilities['capable']) {
                $this->warn('Container does not have required capabilities for Cloud Connect:');
                foreach ($capabilities['errors'] as $error) {
                    $this->warn("  - {$error}");
                }
                return Command::SUCCESS;
            }

            // Attempt auto-reconnect
            if ($cloudConnectService->attemptAutoReconnect()) {
                $this->info('Successfully reconnected to Cloud Connect tunnel!');
                return Command::SUCCESS;
            } else {
                $this->warn('Auto-reconnect did not succeed. Manual reconnection may be required.');
                return Command::SUCCESS;
            }
        } catch (Exception $e) {
            $this->error('Auto-reconnect failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
