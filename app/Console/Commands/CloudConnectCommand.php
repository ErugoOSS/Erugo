<?php

namespace App\Console\Commands;

use App\Services\CloudConnectService;
use Illuminate\Console\Command;
use Exception;

class CloudConnectCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloud-connect:{action : The action to perform (status, up, down, heartbeat)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage the Cloud Connect tunnel';

    /**
     * Execute the console command.
     */
    public function handle(CloudConnectService $cloudConnectService): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'status' => $this->showStatus($cloudConnectService),
            'up' => $this->bringUp($cloudConnectService),
            'down' => $this->bringDown($cloudConnectService),
            'heartbeat' => $this->sendHeartbeat($cloudConnectService),
            default => $this->showHelp(),
        };
    }

    /**
     * Show current tunnel status
     */
    protected function showStatus(CloudConnectService $cloudConnectService): int
    {
        $status = $cloudConnectService->getStatus();

        $this->info('Cloud Connect Status');
        $this->line('');

        // Capabilities
        $this->line('<fg=yellow>Capabilities:</>');
        $caps = $status['capabilities'];
        $this->line('  NET_ADMIN: ' . ($caps['has_net_admin'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line('  TUN Device: ' . ($caps['has_tun_device'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line('  WireGuard Tools: ' . ($caps['has_wg_tools'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        
        if (!$caps['capable']) {
            $this->line('');
            $this->error('Container is missing required capabilities:');
            foreach ($caps['errors'] as $error) {
                $this->line("  - {$error}");
            }
        }

        $this->line('');
        $this->line('<fg=yellow>Connection:</>');
        $this->line('  Logged In: ' . ($status['is_logged_in'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line('  Has Instance: ' . ($status['has_instance'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));
        $this->line('  Status: ' . $this->formatStatus($status['status']));
        $this->line('  Tunnel Active: ' . ($status['tunnel_active'] ? '<fg=green>Yes</>' : '<fg=red>No</>'));

        if ($status['subdomain']) {
            $this->line('');
            $this->line('<fg=yellow>Domain:</>');
            $this->line('  Subdomain: ' . $status['subdomain']);
            $this->line('  Full Domain: ' . ($status['full_domain'] ?? 'N/A'));
            $this->line('  Tunnel IP: ' . ($status['tunnel_ip'] ?? 'N/A'));
        }

        if ($status['user_email']) {
            $this->line('');
            $this->line('<fg=yellow>Account:</>');
            $this->line('  Email: ' . $status['user_email']);
            $this->line('  Subscription: ' . ($status['subscription_status'] ?? 'none'));
            $this->line('  Plan: ' . ($status['subscription_plan'] ?? 'N/A'));
        }

        if ($status['last_error']) {
            $this->line('');
            $this->error('Last Error: ' . $status['last_error']);
        }

        return 0;
    }

    /**
     * Bring up the tunnel
     */
    protected function bringUp(CloudConnectService $cloudConnectService): int
    {
        $this->info('Bringing up Cloud Connect tunnel...');

        try {
            $result = $cloudConnectService->connect();
            $this->info('Tunnel connected successfully!');
            
            if (!empty($result['domains']['subdomain'])) {
                $this->line('Your instance is accessible at: https://' . $result['domains']['subdomain']);
            }

            return 0;
        } catch (Exception $e) {
            $this->error('Failed to connect: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Bring down the tunnel
     */
    protected function bringDown(CloudConnectService $cloudConnectService): int
    {
        $this->info('Bringing down Cloud Connect tunnel...');

        try {
            $cloudConnectService->disconnect();
            $this->info('Tunnel disconnected.');
            return 0;
        } catch (Exception $e) {
            $this->error('Failed to disconnect: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Send heartbeat
     */
    protected function sendHeartbeat(CloudConnectService $cloudConnectService): int
    {
        $this->info('Sending heartbeat...');

        try {
            $result = $cloudConnectService->sendHeartbeat();
            $this->info('Heartbeat sent successfully.');
            $this->line('Next heartbeat in: ' . ($result['next_heartbeat_in'] ?? 60) . ' seconds');
            return 0;
        } catch (Exception $e) {
            $this->error('Heartbeat failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show help
     */
    protected function showHelp(): int
    {
        $this->error('Invalid action. Available actions: status, up, down, heartbeat');
        return 1;
    }

    /**
     * Format status for display
     */
    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'connected' => '<fg=green>Connected</>',
            'disconnected' => '<fg=gray>Disconnected</>',
            'connecting' => '<fg=yellow>Connecting</>',
            'reconnecting' => '<fg=yellow>Reconnecting</>',
            'error' => '<fg=red>Error</>',
            default => $status,
        };
    }
}

