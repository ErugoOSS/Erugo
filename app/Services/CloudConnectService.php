<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Exception;

class CloudConnectService
{
    protected SettingsService $settingsService;
    protected string $apiUrl;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        $this->apiUrl = $this->getSetting('cloud_connect_api_url') ?? 'https://api.erugo.cloud/v1';
    }

    /**
     * Check if the container has the required capabilities for WireGuard
     */
    public function checkCapabilities(): array
    {
        $hasNetAdmin = false;
        $hasTunDevice = false;
        $hasWgTools = false;
        $errors = [];

        // Check for /dev/net/tun
        if (file_exists('/dev/net/tun')) {
            $hasTunDevice = true;
        } else {
            $errors[] = 'TUN device not available. Add "devices: [/dev/net/tun:/dev/net/tun]" to your docker-compose.yml';
        }

        // Check for wg command
        $wgPath = trim(shell_exec('which wg 2>/dev/null') ?? '');
        if (!empty($wgPath)) {
            $hasWgTools = true;
        } else {
            $errors[] = 'WireGuard tools not installed in container';
        }

        // Check for NET_ADMIN capability
        // We check CapBnd (bounding set) because the container has the capability,
        // even if the current process (running as non-root) doesn't have it in CapEff.
        // We'll use sudo for actual WireGuard commands.
        
        // Method 1: Check /proc/self/status for CapBnd (bounding set)
        $capBndLine = shell_exec('grep "^CapBnd:" /proc/self/status 2>/dev/null');
        if ($capBndLine) {
            // Parse the hex capability mask
            $capHex = trim(str_replace('CapBnd:', '', $capBndLine));
            $capInt = hexdec($capHex);
            // CAP_NET_ADMIN is bit 12 (value 4096 = 0x1000)
            if ($capInt & (1 << 12)) {
                $hasNetAdmin = true;
            }
        }
        
        // Method 2: Also check CapEff in case we're running as root
        if (!$hasNetAdmin) {
            $capEffLine = shell_exec('grep "^CapEff:" /proc/self/status 2>/dev/null');
            if ($capEffLine) {
                $capHex = trim(str_replace('CapEff:', '', $capEffLine));
                $capInt = hexdec($capHex);
                if ($capInt & (1 << 12)) {
                    $hasNetAdmin = true;
                }
            }
        }
        
        // Method 3: Try running a command with sudo to verify we can actually use NET_ADMIN
        if (!$hasNetAdmin && $hasWgTools && $hasTunDevice) {
            $testResult = shell_exec('sudo -n ip link add wg-test type wireguard 2>&1');
            if ($testResult === null || strpos($testResult, 'Operation not permitted') === false) {
                $hasNetAdmin = true;
                shell_exec('sudo -n ip link delete wg-test 2>/dev/null');
            }
        }
        
        if (!$hasNetAdmin) {
            $errors[] = 'NET_ADMIN capability not available. Add "cap_add: [NET_ADMIN]" to your docker-compose.yml';
        }

        return [
            'capable' => $hasNetAdmin && $hasTunDevice && $hasWgTools,
            'has_net_admin' => $hasNetAdmin,
            'has_tun_device' => $hasTunDevice,
            'has_wg_tools' => $hasWgTools,
            'errors' => $errors,
        ];
    }

    /**
     * Get the current connection status
     */
    public function getStatus(bool $refreshProfile = false): array
    {
        $capabilities = $this->checkCapabilities();
        $isLoggedIn = !empty($this->getSetting('cloud_connect_access_token'));
        $hasInstance = !empty($this->getSetting('cloud_connect_instance_id'));
        
        // Fetch user profile from API if logged in and either:
        // - account_status is missing, OR
        // - refreshProfile is requested (e.g., user clicked "Check Again")
        if ($isLoggedIn && ($refreshProfile || empty($this->getSetting('cloud_connect_account_status')))) {
            try {
                $this->getUserProfile();
            } catch (Exception $e) {
                // Ignore errors - we'll just return cached values
            }
        }
        
        // Check actual WireGuard interface status (using sudo)
        $tunnelActive = false;
        if ($capabilities['capable']) {
            $wgShow = shell_exec('sudo wg show wg0 2>/dev/null');
            $tunnelActive = !empty($wgShow) && strpos($wgShow, 'interface: wg0') !== false;
        }

        $status = $this->getSetting('cloud_connect_status') ?? 'disconnected';
        
        // Sync status with actual tunnel state
        if ($tunnelActive && $status !== 'connected') {
            $this->setSetting('cloud_connect_status', 'connected');
            $status = 'connected';
        } elseif (!$tunnelActive && $status === 'connected') {
            $this->setSetting('cloud_connect_status', 'disconnected');
            $status = 'disconnected';
        }

        return [
            'capabilities' => $capabilities,
            'is_logged_in' => $isLoggedIn,
            'has_instance' => $hasInstance,
            'status' => $status,
            'tunnel_active' => $tunnelActive,
            'subdomain' => $this->getSetting('cloud_connect_subdomain'),
            'full_domain' => $this->getSetting('cloud_connect_full_domain'),
            'tunnel_ip' => $this->getSetting('cloud_connect_tunnel_ip'),
            'user_email' => $this->getSetting('cloud_connect_user_email'),
            'subscription_status' => $this->getSetting('cloud_connect_subscription_status'),
            'subscription_plan' => $this->getSetting('cloud_connect_subscription_plan'),
            'account_status' => $this->getSetting('cloud_connect_account_status'),
            'last_error' => $this->getSetting('cloud_connect_last_error'),
            'instance_id' => $this->getSetting('cloud_connect_instance_id'),
            'last_heartbeat_at' => $this->getSetting('cloud_connect_last_heartbeat_at'),
            'last_heartbeat_success' => $this->getSetting('cloud_connect_last_heartbeat_success') === 'true',
            'last_heartbeat_error' => $this->getSetting('cloud_connect_last_heartbeat_error'),
        ];
    }

    /**
     * Register a new account with the cloud service
     */
    public function register(array $data): array
    {
        $response = Http::post("{$this->apiUrl}/auth/register", [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'password_confirmation' => $data['password_confirmation'],
            'accept_terms' => $data['accept_terms'] ?? true,
            'accept_privacy' => $data['accept_privacy'] ?? true,
            'accept_marketing' => $data['accept_marketing'] ?? false,
            'erugo_version' => config('app.version', '1.0.0'),
        ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Registration failed';
            throw new Exception($error);
        }

        return $response->json();
    }

    /**
     * Login to the cloud service
     */
    public function login(string $email, string $password): array
    {
        $response = Http::post("{$this->apiUrl}/auth/login", [
            'email' => $email,
            'password' => $password,
        ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Login failed';
            throw new Exception($error);
        }

        $data = $response->json();

        // Store tokens
        $this->setEncryptedSetting('cloud_connect_access_token', $data['access_token']);
        $this->setEncryptedSetting('cloud_connect_refresh_token', $data['refresh_token']);
        $this->setSetting('cloud_connect_token_expires_at', time() + ($data['expires_in'] ?? 900));
        $this->setSetting('cloud_connect_user_email', $data['user']['email'] ?? $email);
        $this->setSetting('cloud_connect_subscription_status', $data['user']['subscription_status'] ?? 'none');
        $this->setSetting('cloud_connect_subscription_plan', $data['user']['subscription_plan'] ?? null);
        $this->setSetting('cloud_connect_account_status', $data['user']['account_status'] ?? $data['user']['status'] ?? null);

        return $data;
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(): array
    {
        return $this->apiRequest('POST', '/auth/resend-verification');
    }

    /**
     * Refresh the access token
     */
    public function refreshToken(): bool
    {
        $refreshToken = $this->getEncryptedSetting('cloud_connect_refresh_token');
        if (empty($refreshToken)) {
            return false;
        }

        try {
            $response = Http::post("{$this->apiUrl}/auth/refresh", [
                'refresh_token' => $refreshToken,
            ]);

            if (!$response->successful()) {
                // Token refresh failed, clear auth state
                $this->clearAuthState();
                return false;
            }

            $data = $response->json();
            $this->setEncryptedSetting('cloud_connect_access_token', $data['access_token']);
            $this->setSetting('cloud_connect_token_expires_at', time() + ($data['expires_in'] ?? 900));

            return true;
        } catch (Exception $e) {
            Log::error('Cloud Connect token refresh failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Make an authenticated API request
     */
    protected function apiRequest(string $method, string $endpoint, array $data = [], bool $useInstanceToken = false): array
    {
        $token = $useInstanceToken
            ? $this->getEncryptedSetting('cloud_connect_instance_token')
            : $this->getEncryptedSetting('cloud_connect_access_token');

        if (empty($token)) {
            throw new Exception('Not authenticated');
        }

        // Check if access token needs refresh (not for instance tokens)
        if (!$useInstanceToken) {
            $expiresAt = (int) $this->getSetting('cloud_connect_token_expires_at');
            if ($expiresAt && time() >= $expiresAt - 60) {
                if (!$this->refreshToken()) {
                    throw new Exception('Session expired. Please login again.');
                }
                $token = $this->getEncryptedSetting('cloud_connect_access_token');
            }
        }

        $request = Http::withToken($token);

        // For POST/PATCH with empty data, send empty object {} instead of array []
        $postData = empty($data) ? (object)[] : $data;
        
        $response = match (strtoupper($method)) {
            'GET' => $request->get("{$this->apiUrl}{$endpoint}", $data),
            'POST' => $request->post("{$this->apiUrl}{$endpoint}", $postData),
            'PATCH' => $request->patch("{$this->apiUrl}{$endpoint}", $postData),
            'DELETE' => $request->delete("{$this->apiUrl}{$endpoint}"),
            default => throw new Exception("Unsupported HTTP method: {$method}"),
        };

        if (!$response->successful()) {
            $errorBody = $response->json();
            
            // Log full error details for debugging
            Log::error('Cloud Connect API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'response' => $errorBody,
            ]);
            
            // For 409 conflicts (like SUBDOMAIN_OWNED_BY_USER), pass through the full error structure
            if ($response->status() === 409 && isset($errorBody['error']['code'])) {
                throw new Exception(json_encode($errorBody['error']));
            }
            
            $error = $errorBody['error']['message'] ?? "API request failed: {$response->status()}";
            throw new Exception($error);
        }

        return $response->json() ?? [];
    }

    /**
     * Get subscription status
     */
    public function getSubscription(): array
    {
        $data = $this->apiRequest('GET', '/billing/subscription');
        
        // Update local cache
        $this->setSetting('cloud_connect_subscription_status', $data['status'] ?? 'none');
        $this->setSetting('cloud_connect_subscription_plan', $data['plan'] ?? null);
        
        // Also update account status if provided
        if (isset($data['account_status'])) {
            $this->setSetting('cloud_connect_account_status', $data['account_status']);
        }

        return $data;
    }

    /**
     * Get user profile from cloud API
     */
    public function getUserProfile(): array
    {
        $data = $this->apiRequest('GET', '/user');
        
        // The /user endpoint returns the user object directly (not wrapped in 'user' key)
        $user = $data;
        $this->setSetting('cloud_connect_user_email', $user['email'] ?? null);
        $this->setSetting('cloud_connect_account_status', $user['account_status'] ?? $user['status'] ?? null);
        $this->setSetting('cloud_connect_subscription_status', $user['subscription_status'] ?? 'none');
        $this->setSetting('cloud_connect_subscription_plan', $user['subscription_plan'] ?? null);

        return $data;
    }

    /**
     * Get available subscription plans
     */
    public function getPlans(): array
    {
        return $this->apiRequest('GET', '/billing/plans');
    }

    /**
     * Create a checkout session
     */
    public function createCheckout(string $plan): array
    {
        return $this->apiRequest('POST', '/billing/checkout', [
            'plan' => $plan,
        ]);
    }

    /**
     * Create a billing portal session for subscription management
     */
    public function createBillingPortal(string $returnUrl): array
    {
        return $this->apiRequest('POST', '/billing/portal', [
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Get user usage statistics
     */
    public function getUserUsage(): array
    {
        return $this->apiRequest('GET', '/user/usage');
    }

    /**
     * Update user profile
     */
    public function updateUser(array $data): array
    {
        $result = $this->apiRequest('PATCH', '/user', $data);
        
        // Update local cache if name changed
        if (isset($result['name'])) {
            $this->setSetting('cloud_connect_user_name', $result['name']);
        }
        
        return $result;
    }

    /**
     * Request password reset email (no auth required)
     */
    public function forgotPassword(string $email): array
    {
        $response = Http::post("{$this->apiUrl}/auth/forgot-password", [
            'email' => $email,
        ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Failed to send reset email';
            throw new Exception($error);
        }

        return $response->json() ?? ['message' => 'If an account exists, a reset email has been sent'];
    }

    /**
     * Reset password with token (no auth required)
     */
    public function resetPassword(string $token, string $password, string $passwordConfirmation): array
    {
        $response = Http::post("{$this->apiUrl}/auth/reset-password", [
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ]);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Password reset failed';
            throw new Exception($error);
        }

        return $response->json() ?? ['message' => 'Password reset successfully'];
    }

    /**
     * Get a specific instance by ID
     */
    public function getInstance(string $instanceId): array
    {
        return $this->apiRequest('GET', "/instances/{$instanceId}");
    }

    /**
     * Update an instance
     */
    public function updateInstance(string $instanceId, array $data): array
    {
        $result = $this->apiRequest('PATCH', "/instances/{$instanceId}", $data);
        
        // If updating the current connected instance, update local cache
        $currentInstanceId = $this->getSetting('cloud_connect_instance_id');
        if ($currentInstanceId === $instanceId) {
            if (isset($result['subdomain'])) {
                $this->setSetting('cloud_connect_subdomain', $result['subdomain']);
            }
            if (isset($result['full_domain'])) {
                $this->setSetting('cloud_connect_full_domain', $result['full_domain']);
            }
        }
        
        return $result;
    }

    /**
     * Delete an instance
     */
    public function deleteInstance(string $instanceId): array
    {
        $this->apiRequest('DELETE', "/instances/{$instanceId}");
        
        // If deleting the current connected instance, clear local state
        $currentInstanceId = $this->getSetting('cloud_connect_instance_id');
        if ($currentInstanceId === $instanceId) {
            $this->clearInstanceState();
        }
        
        return ['message' => 'Instance deleted successfully'];
    }

    /**
     * Regenerate instance token
     */
    public function regenerateInstanceToken(string $instanceId): array
    {
        $result = $this->apiRequest('POST', "/instances/{$instanceId}/regenerate-token");
        
        // If regenerating token for current instance, update local cache
        $currentInstanceId = $this->getSetting('cloud_connect_instance_id');
        if ($currentInstanceId === $instanceId && isset($result['instance_token'])) {
            $this->setEncryptedSetting('cloud_connect_instance_token', $result['instance_token']);
        }
        
        return $result;
    }

    /**
     * Check subdomain availability
     */
    public function checkSubdomain(string $subdomain): array
    {
        return $this->apiRequest('GET', '/subdomains/check', [
            'subdomain' => $subdomain,
        ]);
    }

    /**
     * Create a new instance
     */
    public function createInstance(string $name, string $subdomain, bool $confirmReclaim = false): array
    {
        $requestData = [
            'name' => $name,
            'subdomain' => $subdomain,
        ];
        
        if ($confirmReclaim) {
            $requestData['confirm_reclaim'] = true;
        }
        
        $data = $this->apiRequest('POST', '/instances', $requestData);

        // Store instance details
        $instance = $data['instance'] ?? [];
        $credentials = $data['credentials'] ?? [];

        $this->setSetting('cloud_connect_instance_id', $instance['id'] ?? null);
        $this->setSetting('cloud_connect_subdomain', $instance['subdomain'] ?? null);
        $this->setSetting('cloud_connect_full_domain', $instance['full_domain'] ?? null);
        $this->setSetting('cloud_connect_tunnel_ip', $instance['tunnel_ip'] ?? null);
        
        if (!empty($credentials['instance_token'])) {
            $this->setEncryptedSetting('cloud_connect_instance_token', $credentials['instance_token']);
        }

        return $data;
    }

    /**
     * Get list of instances
     */
    public function getInstances(): array
    {
        return $this->apiRequest('GET', '/instances');
    }

    /**
     * Generate WireGuard keypair
     */
    public function generateWireGuardKeys(): array
    {
        // Generate private key
        $privateKey = trim(shell_exec('wg genkey 2>/dev/null') ?? '');
        if (empty($privateKey)) {
            throw new Exception('Failed to generate WireGuard private key');
        }

        // Generate public key from private key
        $publicKey = trim(shell_exec("echo '{$privateKey}' | wg pubkey 2>/dev/null") ?? '');
        if (empty($publicKey)) {
            throw new Exception('Failed to generate WireGuard public key');
        }

        // Store keys
        $this->setEncryptedSetting('cloud_connect_private_key', $privateKey);
        $this->setSetting('cloud_connect_public_key', $publicKey);

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Register tunnel with cloud service and get config
     */
    public function registerTunnel(): array
    {
        // Ensure we have keys
        $publicKey = $this->getSetting('cloud_connect_public_key');
        if (empty($publicKey)) {
            $keys = $this->generateWireGuardKeys();
            $publicKey = $keys['public_key'];
        }

        // Register with cloud API using instance token
        $data = $this->apiRequest('POST', '/tunnel/register', [
            'public_key' => $publicKey,
        ], true);

        return $data;
    }

    /**
     * Connect the tunnel
     */
    public function connect(): array
    {
        $capabilities = $this->checkCapabilities();
        if (!$capabilities['capable']) {
            throw new Exception('Container does not have required capabilities: ' . implode(', ', $capabilities['errors']));
        }

        // Get tunnel configuration from cloud
        $tunnelData = $this->registerTunnel();
        $tunnelConfig = $tunnelData['tunnel_config'] ?? [];
        
        if (empty($tunnelConfig)) {
            throw new Exception('Failed to get tunnel configuration from cloud service');
        }

        // Get private key
        $privateKey = $this->getEncryptedSetting('cloud_connect_private_key');
        if (empty($privateKey)) {
            throw new Exception('WireGuard private key not found');
        }

        // Build WireGuard config
        $config = $this->buildWireGuardConfig($privateKey, $tunnelConfig);

        // Write config file using sudo (required for /etc/wireguard)
        $this->writeWireGuardConfig($config);

        // Bring down existing tunnel if any
        shell_exec('sudo wg-quick down wg0 2>/dev/null');

        // Bring up tunnel
        $output = shell_exec('sudo wg-quick up wg0 2>&1');
        
        // Verify tunnel is up
        $wgShow = shell_exec('sudo wg show wg0 2>/dev/null');
        if (empty($wgShow) || strpos($wgShow, 'interface: wg0') === false) {
            $this->setSetting('cloud_connect_status', 'error');
            $this->setSetting('cloud_connect_last_error', 'Failed to bring up WireGuard tunnel: ' . ($output ?? 'Unknown error'));
            throw new Exception('Failed to bring up WireGuard tunnel: ' . ($output ?? 'Unknown error'));
        }

        // Update status
        $this->setSetting('cloud_connect_status', 'connected');
        $this->setSetting('cloud_connect_enabled', 'true');
        $this->setSetting('cloud_connect_last_error', null);

        // Update domain info from response
        if (!empty($tunnelData['domains'])) {
            $this->setSetting('cloud_connect_subdomain', $tunnelData['domains']['subdomain'] ?? null);
        }

        // Send initial heartbeat immediately so UI shows as online
        try {
            $this->sendHeartbeat();
        } catch (Exception $e) {
            // Log but don't fail the connection if heartbeat fails
            Log::warning('Initial heartbeat after connect failed: ' . $e->getMessage());
        }

        return [
            'status' => 'connected',
            'tunnel_config' => $tunnelConfig,
            'domains' => $tunnelData['domains'] ?? [],
        ];
    }

    /**
     * Write WireGuard config file using sudo
     */
    protected function writeWireGuardConfig(string $config): void
    {
        // Create directory if needed
        shell_exec('sudo mkdir -p /etc/wireguard 2>/dev/null');
        
        // Write config via sudo tee
        $escapedConfig = escapeshellarg($config);
        $result = shell_exec("echo {$escapedConfig} | sudo tee /etc/wireguard/wg0.conf > /dev/null 2>&1; echo $?");
        
        if (trim($result) !== '0') {
            throw new Exception('Failed to write WireGuard configuration file');
        }
        
        // Set permissions
        shell_exec('sudo chmod 600 /etc/wireguard/wg0.conf 2>/dev/null');
    }

    /**
     * Build WireGuard configuration file content
     */
    protected function buildWireGuardConfig(string $privateKey, array $tunnelConfig): string
    {
        $interface = $tunnelConfig['interface'] ?? [];
        $peer = $tunnelConfig['peer'] ?? [];

        $config = "[Interface]\n";
        $config .= "PrivateKey = {$privateKey}\n";
        $config .= "Address = " . ($interface['address'] ?? '10.100.0.2/32') . "\n";
        $config .= "\n";
        $config .= "[Peer]\n";
        $config .= "PublicKey = " . ($peer['public_key'] ?? '') . "\n";
        $config .= "Endpoint = " . ($peer['endpoint'] ?? '') . "\n";
        $config .= "AllowedIPs = " . ($peer['allowed_ips'] ?? '10.100.0.0/24') . "\n";
        $config .= "PersistentKeepalive = " . ($peer['persistent_keepalive'] ?? 25) . "\n";

        return $config;
    }

    /**
     * Disconnect the tunnel
     */
    public function disconnect(): array
    {
        // Notify cloud service
        try {
            $this->apiRequest('POST', '/tunnel/disconnect', [], true);
        } catch (Exception $e) {
            Log::warning('Failed to notify cloud of disconnect: ' . $e->getMessage());
        }

        // Bring down tunnel (using sudo)
        shell_exec('sudo wg-quick down wg0 2>/dev/null');

        // Update status
        $this->setSetting('cloud_connect_status', 'disconnected');
        $this->setSetting('cloud_connect_enabled', 'false');

        return [
            'status' => 'disconnected',
        ];
    }

    /**
     * Send heartbeat to cloud service
     */
    public function sendHeartbeat(): array
    {
        try {
            $data = $this->apiRequest('POST', '/tunnel/heartbeat', [], true);

            // Record successful heartbeat
            $this->setSetting('cloud_connect_last_heartbeat_at', now()->toIso8601String());
            $this->setSetting('cloud_connect_last_heartbeat_success', 'true');
            $this->setSetting('cloud_connect_last_heartbeat_error', null);
            
            Log::info('Cloud Connect heartbeat sent successfully');

            return $data;
        } catch (Exception $e) {
            Log::error('Cloud Connect heartbeat failed: ' . $e->getMessage());
            
            // Record failed heartbeat
            $this->setSetting('cloud_connect_last_heartbeat_success', 'false');
            $this->setSetting('cloud_connect_last_heartbeat_error', $e->getMessage());
            
            // Update status to indicate connection issue
            $this->setSetting('cloud_connect_status', 'reconnecting');
            
            throw $e;
        }
    }

    /**
     * Get tunnel status from cloud
     */
    public function getTunnelStatus(): array
    {
        return $this->apiRequest('GET', '/tunnel/status', [], true);
    }

    /**
     * Logout and clear all cloud connect data
     */
    public function logout(): void
    {
        // Disconnect tunnel first
        try {
            $this->disconnect();
        } catch (Exception $e) {
            // Ignore disconnect errors during logout
        }

        $this->clearAuthState();
        $this->clearInstanceState();
    }

    /**
     * Clear authentication state
     */
    protected function clearAuthState(): void
    {
        $this->setSetting('cloud_connect_access_token', null);
        $this->setSetting('cloud_connect_refresh_token', null);
        $this->setSetting('cloud_connect_token_expires_at', null);
        $this->setSetting('cloud_connect_user_email', null);
        $this->setSetting('cloud_connect_subscription_status', null);
        $this->setSetting('cloud_connect_subscription_plan', null);
    }

    /**
     * Clear instance state
     */
    protected function clearInstanceState(): void
    {
        $this->setSetting('cloud_connect_instance_id', null);
        $this->setSetting('cloud_connect_instance_token', null);
        $this->setSetting('cloud_connect_subdomain', null);
        $this->setSetting('cloud_connect_full_domain', null);
        $this->setSetting('cloud_connect_tunnel_ip', null);
        $this->setSetting('cloud_connect_private_key', null);
        $this->setSetting('cloud_connect_public_key', null);
        $this->setSetting('cloud_connect_status', 'disconnected');
        $this->setSetting('cloud_connect_enabled', 'false');
        $this->setSetting('cloud_connect_last_error', null);
    }

    /**
     * Get a setting value
     */
    protected function getSetting(string $key): ?string
    {
        $setting = Setting::where('key', $key)->first();
        return $setting?->value;
    }

    /**
     * Set a setting value
     */
    protected function setSetting(string $key, ?string $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => 'system.cloud_connect',
            ]
        );
    }

    /**
     * Get an encrypted setting value
     */
    protected function getEncryptedSetting(string $key): ?string
    {
        $value = $this->getSetting($key);
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Exception $e) {
            // If decryption fails, the value might not be encrypted
            return $value;
        }
    }

    /**
     * Set an encrypted setting value
     */
    protected function setEncryptedSetting(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->setSetting($key, null);
            return;
        }

        $this->setSetting($key, Crypt::encryptString($value));
    }
}

