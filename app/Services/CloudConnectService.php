<?php

namespace App\Services;

use App\Models\CloudConnectSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Exception;

class CloudConnectService
{
    protected SettingsService $settingsService;
    protected string $apiUrl;
    protected ?CloudConnectSetting $settings = null;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
        $this->apiUrl = $this->getSetting('api_url') ?? 'https://api.erugo.cloud/v1';
    }

    /**
     * Get the singleton cloud connect settings instance
     */
    protected function getSettings(): CloudConnectSetting
    {
        if ($this->settings === null) {
            $this->settings = CloudConnectSetting::getInstance();
        }
        return $this->settings;
    }

    /**
     * Get a setting value from the cloud connect settings table
     */
    protected function getSetting(string $key): mixed
    {
        // Strip the 'cloud_connect_' prefix if present for backward compatibility
        $column = str_replace('cloud_connect_', '', $key);
        
        $settings = $this->getSettings();
        
        // Handle boolean 'enabled' field specially
        if ($column === 'enabled') {
            return $settings->enabled ? 'true' : 'false';
        }
        
        return $settings->$column ?? null;
    }

    /**
     * Set a setting value in the cloud connect settings table
     */
    protected function setSetting(string $key, mixed $value): void
    {
        // Strip the 'cloud_connect_' prefix if present for backward compatibility
        $column = str_replace('cloud_connect_', '', $key);
        
        $settings = $this->getSettings();
        
        // Handle boolean 'enabled' field specially
        if ($column === 'enabled') {
            $settings->enabled = $value === 'true' || $value === true || $value === '1';
        } else {
            $settings->$column = $value;
        }
        
        $settings->save();
        
        // Refresh the cached instance
        $this->settings = $settings->fresh();
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

    /**
     * Get the persistent Erugo instance GUID
     * This identifier persists across container restarts and is used to reconnect to existing tunnels
     * If no GUID exists, one will be generated and saved (for development environments)
     */
    public function getErugoInstanceGuid(): ?string
    {
        // First check environment variable (set by container startup script)
        $guid = env('ERUGO_INSTANCE_GUID');
        
        // Fallback to reading from file directly if env var not set
        if (empty($guid)) {
            $guidFile = storage_path('instance.guid');
            if (file_exists($guidFile)) {
                $guid = trim(file_get_contents($guidFile));
            }
        }
        
        // If still no GUID, generate one (for development environments without the startup script)
        if (empty($guid)) {
            $guid = $this->generateAndSaveInstanceGuid();
        }
        
        return $guid ?: null;
    }

    /**
     * Generate a new instance GUID and save it to storage
     * Used in development environments where the container startup script doesn't run
     */
    protected function generateAndSaveInstanceGuid(): ?string
    {
        try {
            // Generate UUID-like format
            $guid = sprintf(
                '%s-%s-%s-%s-%s',
                bin2hex(random_bytes(4)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(6))
            );
            
            $guidFile = storage_path('instance.guid');
            file_put_contents($guidFile, $guid);
            
            Log::info('Generated new Erugo instance GUID', ['guid' => $guid]);
            
            return $guid;
        } catch (Exception $e) {
            Log::error('Failed to generate Erugo instance GUID: ' . $e->getMessage());
            return null;
        }
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
        $isLoggedIn = !empty($this->getSetting('access_token'));
        $hasInstance = !empty($this->getSetting('instance_id'));
        
        // Always fetch fresh subscription data from API when logged in
        // Fall back to cached values only if API call fails
        $subscriptionCancelAtPeriodEnd = $this->getSetting('subscription_cancel_at_period_end') === 'true';
        $subscriptionCurrentPeriodEnd = $this->getSetting('subscription_current_period_end');
        
        if ($isLoggedIn) {
            try {
                // Fetch user profile if needed
                if ($refreshProfile || empty($this->getSetting('account_status'))) {
                    $this->getUserProfile();
                }
                
                // Always fetch fresh subscription data to get current cancel_at_period_end status
                $subscriptionData = $this->getSubscription();
                Log::info('Fresh subscription data from API', $subscriptionData);
                $subscriptionCancelAtPeriodEnd = $subscriptionData['cancel_at_period_end'] ?? false;
                $subscriptionCurrentPeriodEnd = $subscriptionData['current_period_end'] ?? null;
                Log::info('Parsed subscription values', [
                    'cancel_at_period_end' => $subscriptionCancelAtPeriodEnd,
                    'current_period_end' => $subscriptionCurrentPeriodEnd,
                ]);
            } catch (Exception $e) {
                // API call failed - use cached values (already set above)
                Log::warning('Failed to fetch fresh subscription data: ' . $e->getMessage());
            }
        }
        
        // Check actual WireGuard interface status (using sudo)
        $tunnelActive = false;
        if ($capabilities['capable']) {
            $wgShow = shell_exec('sudo wg show wg0 2>/dev/null');
            $tunnelActive = !empty($wgShow) && strpos($wgShow, 'interface: wg0') !== false;
        }

        $status = $this->getSetting('status') ?? 'disconnected';
        
        // Sync status with actual tunnel state
        if ($tunnelActive && $status !== 'connected') {
            $this->setSetting('status', 'connected');
            $status = 'connected';
        } elseif (!$tunnelActive && $status === 'connected') {
            $this->setSetting('status', 'disconnected');
            $status = 'disconnected';
        }

        return [
            'capabilities' => $capabilities,
            'is_logged_in' => $isLoggedIn,
            'has_instance' => $hasInstance,
            'status' => $status,
            'tunnel_active' => $tunnelActive,
            'subdomain' => $this->getSetting('subdomain'),
            'full_domain' => $this->getSetting('full_domain'),
            'tunnel_ip' => $this->getSetting('tunnel_ip'),
            'user_email' => $this->getSetting('user_email'),
            'subscription_status' => $this->getSetting('subscription_status'),
            'subscription_plan' => $this->getSetting('subscription_plan'),
            'subscription_cancel_at_period_end' => $subscriptionCancelAtPeriodEnd,
            'subscription_current_period_end' => $subscriptionCurrentPeriodEnd,
            'account_status' => $this->getSetting('account_status'),
            'last_error' => $this->getSetting('last_error'),
            'instance_id' => $this->getSetting('instance_id'),
            'erugo_instance_guid' => $this->getErugoInstanceGuid(),
            'last_heartbeat_at' => $this->getSetting('last_heartbeat_at'),
            'last_heartbeat_success' => $this->getSetting('last_heartbeat_success') === 'true',
            'last_heartbeat_error' => $this->getSetting('last_heartbeat_error'),
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
        $this->setEncryptedSetting('access_token', $data['access_token']);
        $this->setEncryptedSetting('refresh_token', $data['refresh_token']);
        $this->setSetting('token_expires_at', time() + ($data['expires_in'] ?? 900));
        $this->setSetting('user_email', $data['user']['email'] ?? $email);
        $this->setSetting('subscription_status', $data['user']['subscription_status'] ?? 'none');
        $this->setSetting('subscription_plan', $data['user']['subscription_plan'] ?? null);
        $this->setSetting('account_status', $data['user']['account_status'] ?? $data['user']['status'] ?? null);

        // After successful login, check if user has an instance matching this Erugo's GUID
        // If found, automatically re-link it so the user doesn't have to manually select it
        $this->attemptAutoLinkInstance();

        return $data;
    }

    /**
     * Attempt to automatically link an existing instance that matches this Erugo's GUID
     * This is called after login to reconnect a previously linked instance
     * Does not connect the tunnel - just links the instance
     */
    protected function attemptAutoLinkInstance(): void
    {
        $erugoInstanceGuid = $this->getErugoInstanceGuid();
        if (empty($erugoInstanceGuid)) {
            Log::debug('Auto-link skipped: No Erugo instance GUID available');
            return;
        }

        // Check if we already have a valid instance configured
        $currentInstanceId = $this->getSetting('instance_id');
        $instanceToken = $this->getEncryptedSetting('instance_token');
        
        if (!empty($currentInstanceId) && !empty($instanceToken)) {
            Log::debug('Auto-link skipped: Instance already configured locally');
            return;
        }

        // Try to find an existing instance by our GUID
        Log::info('Auto-link: Searching for existing instance by Erugo GUID after login');
        $existingInstance = $this->findInstanceByErugoGuid();
        
        if ($existingInstance) {
            Log::info('Auto-link: Found existing instance, linking', [
                'instance_id' => $existingInstance['id'],
                'subdomain' => $existingInstance['subdomain'] ?? null,
            ]);
            
            try {
                // Link to the existing instance (this regenerates the token and stores instance details)
                $this->linkInstance($existingInstance['id']);
                Log::info('Auto-link: Successfully linked to existing instance');
            } catch (Exception $e) {
                Log::warning('Auto-link: Failed to link to existing instance: ' . $e->getMessage());
                // Don't throw - login was successful, linking is just a convenience
            }
        } else {
            Log::debug('Auto-link: No existing instance found for this Erugo GUID');
        }
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
        $refreshToken = $this->getEncryptedSetting('refresh_token');
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
            $this->setEncryptedSetting('access_token', $data['access_token']);
            $this->setSetting('token_expires_at', time() + ($data['expires_in'] ?? 900));

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
            ? $this->getEncryptedSetting('instance_token')
            : $this->getEncryptedSetting('access_token');

        if (empty($token)) {
            throw new Exception('Not authenticated');
        }

        // Check if access token needs refresh (not for instance tokens)
        if (!$useInstanceToken) {
            $expiresAt = (int) $this->getSetting('token_expires_at');
            if ($expiresAt && time() >= $expiresAt - 60) {
                if (!$this->refreshToken()) {
                    throw new Exception('Session expired. Please login again.');
                }
                $token = $this->getEncryptedSetting('access_token');
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
        $this->setSetting('subscription_status', $data['status'] ?? 'none');
        $this->setSetting('subscription_plan', $data['plan'] ?? null);
        $this->setSetting('subscription_cancel_at_period_end', ($data['cancel_at_period_end'] ?? false) ? 'true' : 'false');
        $this->setSetting('subscription_current_period_end', $data['current_period_end'] ?? null);
        
        // Also update account status if provided
        if (isset($data['account_status'])) {
            $this->setSetting('account_status', $data['account_status']);
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
        $this->setSetting('user_email', $user['email'] ?? null);
        $this->setSetting('account_status', $user['account_status'] ?? $user['status'] ?? null);
        $this->setSetting('subscription_status', $user['subscription_status'] ?? 'none');
        $this->setSetting('subscription_plan', $user['subscription_plan'] ?? null);

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
     * Change subscription plan (between paid plans)
     */
    public function changePlan(string $plan): array
    {
        $result = $this->apiRequest('PATCH', '/billing/subscription', [
            'plan' => $plan,
        ]);
        
        // Update local cache
        if (isset($result['plan'])) {
            $this->setSetting('subscription_plan', $result['plan']);
        }
        
        return $result;
    }

    /**
     * Cancel subscription at period end
     */
    public function cancelSubscription(): array
    {
        $result = $this->apiRequest('POST', '/billing/subscription/cancel');
        
        // Update local cache with cancellation status
        $this->setSetting('subscription_cancel_at_period_end', 'true');
        if (isset($result['current_period_end'])) {
            $this->setSetting('subscription_current_period_end', $result['current_period_end']);
        }
        
        return $result;
    }

    /**
     * Reactivate a cancelled subscription
     */
    public function reactivateSubscription(): array
    {
        $result = $this->apiRequest('POST', '/billing/subscription/reactivate');
        
        // Update local cache - cancellation is no longer scheduled
        $this->setSetting('subscription_cancel_at_period_end', 'false');
        
        return $result;
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
            $this->setSetting('user_name', $result['name']);
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
        $currentInstanceId = $this->getSetting('instance_id');
        if ($currentInstanceId === $instanceId) {
            if (isset($result['subdomain'])) {
                $this->setSetting('subdomain', $result['subdomain']);
            }
            if (isset($result['full_domain'])) {
                $this->setSetting('full_domain', $result['full_domain']);
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
        $currentInstanceId = $this->getSetting('instance_id');
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
        $currentInstanceId = $this->getSetting('instance_id');
        if ($currentInstanceId === $instanceId && isset($result['instance_token'])) {
            $this->setEncryptedSetting('instance_token', $result['instance_token']);
        }
        
        return $result;
    }

    /**
     * Link an existing instance to this Erugo installation
     * This regenerates the instance token and stores all instance details locally
     */
    public function linkInstance(string $instanceId): array
    {
        // First get the instance details
        $instance = $this->apiRequest('GET', "/instances/{$instanceId}");
        
        // Regenerate the token to get a new one for this installation
        $tokenResult = $this->apiRequest('POST', "/instances/{$instanceId}/regenerate-token");
        
        if (empty($tokenResult['instance_token'])) {
            throw new Exception('Failed to get instance token');
        }
        
        // Store instance details locally
        $this->setSetting('instance_id', $instance['id'] ?? $instanceId);
        $this->setSetting('subdomain', $instance['subdomain'] ?? null);
        $this->setSetting('full_domain', $instance['full_domain'] ?? null);
        $this->setSetting('tunnel_ip', $instance['tunnel_ip'] ?? null);
        $this->setEncryptedSetting('instance_token', $tokenResult['instance_token']);
        
        // Clear any previous WireGuard keys so new ones will be generated on connect
        $this->setSetting('private_key', null);
        $this->setSetting('public_key', null);
        
        return [
            'instance' => $instance,
            'linked' => true,
        ];
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
        
        // Include the persistent Erugo instance GUID for reconnection after container restarts
        $erugoInstanceGuid = $this->getErugoInstanceGuid();
        if ($erugoInstanceGuid) {
            $requestData['erugo_instance_id'] = $erugoInstanceGuid;
        }
        
        if ($confirmReclaim) {
            $requestData['confirm_reclaim'] = true;
        }
        
        $data = $this->apiRequest('POST', '/instances', $requestData);

        // Store instance details
        $instance = $data['instance'] ?? [];
        $credentials = $data['credentials'] ?? [];

        $this->setSetting('instance_id', $instance['id'] ?? null);
        $this->setSetting('subdomain', $instance['subdomain'] ?? null);
        $this->setSetting('full_domain', $instance['full_domain'] ?? null);
        $this->setSetting('tunnel_ip', $instance['tunnel_ip'] ?? null);
        
        if (!empty($credentials['instance_token'])) {
            $this->setEncryptedSetting('instance_token', $credentials['instance_token']);
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
     * Find an existing instance by this Erugo's persistent GUID
     * Returns the instance if found, null otherwise
     */
    public function findInstanceByErugoGuid(): ?array
    {
        $erugoInstanceGuid = $this->getErugoInstanceGuid();
        if (empty($erugoInstanceGuid)) {
            return null;
        }

        try {
            $response = $this->getInstances();
            $instances = $response['instances'] ?? [];
            
            foreach ($instances as $instance) {
                if (isset($instance['erugo_instance_id']) && $instance['erugo_instance_id'] === $erugoInstanceGuid) {
                    return $instance;
                }
            }
        } catch (Exception $e) {
            Log::warning('Failed to search for existing instance by GUID: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Attempt to automatically reconnect to an existing instance
     * This is called on container startup when cloud connect was previously enabled
     * Returns true if successfully reconnected, false otherwise
     */
    public function attemptAutoReconnect(): bool
    {
        $erugoInstanceGuid = $this->getErugoInstanceGuid();
        if (empty($erugoInstanceGuid)) {
            Log::debug('Auto-reconnect skipped: No Erugo instance GUID available');
            return false;
        }

        // Check if we're logged in
        $accessToken = $this->getEncryptedSetting('access_token');
        if (empty($accessToken)) {
            Log::debug('Auto-reconnect skipped: Not logged in to Cloud Connect');
            return false;
        }

        // Check if we already have a valid instance configured
        $currentInstanceId = $this->getSetting('instance_id');
        $instanceToken = $this->getEncryptedSetting('instance_token');
        
        if (!empty($currentInstanceId) && !empty($instanceToken)) {
            Log::debug('Auto-reconnect: Instance already configured, attempting to connect tunnel');
            try {
                $this->connect();
                return true;
            } catch (Exception $e) {
                Log::warning('Auto-reconnect: Failed to connect with existing instance config: ' . $e->getMessage());
                // Fall through to try finding instance by GUID
            }
        }

        // Try to find an existing instance by our GUID
        Log::info('Auto-reconnect: Searching for existing instance by Erugo GUID');
        $existingInstance = $this->findInstanceByErugoGuid();
        
        if ($existingInstance) {
            Log::info('Auto-reconnect: Found existing instance', [
                'instance_id' => $existingInstance['id'],
                'subdomain' => $existingInstance['subdomain'] ?? null,
            ]);
            
            try {
                // Link to the existing instance (this regenerates the token)
                $this->linkInstance($existingInstance['id']);
                
                // Now connect the tunnel
                $this->connect();
                
                Log::info('Auto-reconnect: Successfully reconnected to existing instance');
                return true;
            } catch (Exception $e) {
                Log::error('Auto-reconnect: Failed to link/connect to existing instance: ' . $e->getMessage());
                return false;
            }
        }

        Log::debug('Auto-reconnect: No existing instance found for this Erugo GUID');
        return false;
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
        $this->setEncryptedSetting('private_key', $privateKey);
        $this->setSetting('public_key', $publicKey);

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
        $publicKey = $this->getSetting('public_key');
        if (empty($publicKey)) {
            $keys = $this->generateWireGuardKeys();
            $publicKey = $keys['public_key'];
        }

        // Register with cloud API using instance token
        $data = $this->apiRequest('POST', '/tunnel/register', [
            'public_key' => $publicKey,
        ], true);

        Log::info('Tunnel register API response:', $data);

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
        $privateKey = $this->getEncryptedSetting('private_key');
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
            $this->setSetting('status', 'error');
            $this->setSetting('last_error', 'Failed to bring up WireGuard tunnel: ' . ($output ?? 'Unknown error'));
            throw new Exception('Failed to bring up WireGuard tunnel: ' . ($output ?? 'Unknown error'));
        }

        // Update status
        $this->setSetting('status', 'connected');
        $this->setSetting('enabled', 'true');
        $this->setSetting('last_error', null);

        // Update domain info from response
        if (!empty($tunnelData['domains'])) {
            $this->setSetting('subdomain', $tunnelData['domains']['subdomain'] ?? null);
        }

        // Store heartbeat endpoint info from response (used for sending heartbeats via tunnel)
        if (!empty($tunnelData['heartbeat'])) {
            $this->setSetting('heartbeat_endpoint', $tunnelData['heartbeat']['endpoint'] ?? null);
            $this->setSetting('heartbeat_interval', $tunnelData['heartbeat']['interval'] ?? '60');
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
        // Set MTU to 1420 to account for WireGuard overhead (60 bytes) and avoid fragmentation
        // This improves throughput especially for large file transfers
        $config .= "MTU = " . ($interface['mtu'] ?? 1420) . "\n";
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
        $this->setSetting('status', 'disconnected');
        $this->setSetting('enabled', 'false');

        return [
            'status' => 'disconnected',
        ];
    }

    /**
     * Send heartbeat to cloud service via the WireGuard tunnel
     * This proves the tunnel is actually working by sending the heartbeat
     * through the internal tunnel endpoint rather than the public API
     */
    public function sendHeartbeat(): array
    {
        try {
            // Get the internal heartbeat endpoint (set during tunnel registration)
            $heartbeatEndpoint = $this->getSetting('heartbeat_endpoint');
            
            if (empty($heartbeatEndpoint)) {
                throw new Exception('Heartbeat endpoint not configured - tunnel may not be properly registered');
            }

            // Get instance token for authentication
            $instanceToken = $this->getEncryptedSetting('instance_token');
            if (empty($instanceToken)) {
                throw new Exception('Instance token not found');
            }

            // Send heartbeat through the tunnel to the internal endpoint
            $response = Http::withToken($instanceToken)
                ->timeout(10)
                ->post($heartbeatEndpoint, [
                    'timestamp' => now()->toIso8601String(),
                ]);

            if (!$response->successful()) {
                $error = $response->json('error.message') ?? "Heartbeat failed: {$response->status()}";
                throw new Exception($error);
            }

            $data = $response->json() ?? [];

            // Record successful heartbeat
            $this->setSetting('last_heartbeat_at', now()->toIso8601String());
            $this->setSetting('last_heartbeat_success', 'true');
            $this->setSetting('last_heartbeat_error', null);
            
            Log::info('Cloud Connect heartbeat sent successfully via tunnel', [
                'endpoint' => $heartbeatEndpoint,
                'next_heartbeat_in' => $data['next_heartbeat_in'] ?? null,
            ]);

            return $data;
        } catch (Exception $e) {
            Log::error('Cloud Connect heartbeat failed: ' . $e->getMessage());
            
            // Record failed heartbeat
            $this->setSetting('last_heartbeat_success', 'false');
            $this->setSetting('last_heartbeat_error', $e->getMessage());
            
            // Update status to indicate connection issue
            $this->setSetting('status', 'reconnecting');
            
            throw $e;
        }
    }

    /**
     * Get tunnel status from cloud
     */
    public function getTunnelStatus(): array
    {
        $data = $this->apiRequest('GET', '/tunnel/status', [], true);
        
        // Update heartbeat endpoint info if provided
        if (!empty($data['heartbeat'])) {
            $this->setSetting('heartbeat_endpoint', $data['heartbeat']['endpoint'] ?? null);
            $this->setSetting('heartbeat_interval', $data['heartbeat']['interval'] ?? '60');
        }
        
        return $data;
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
        $this->setSetting('access_token', null);
        $this->setSetting('refresh_token', null);
        $this->setSetting('token_expires_at', null);
        $this->setSetting('user_email', null);
        $this->setSetting('subscription_status', null);
        $this->setSetting('subscription_plan', null);
    }

    /**
     * Clear instance state
     */
    protected function clearInstanceState(): void
    {
        $this->setSetting('instance_id', null);
        $this->setSetting('instance_token', null);
        $this->setSetting('subdomain', null);
        $this->setSetting('full_domain', null);
        $this->setSetting('tunnel_ip', null);
        $this->setSetting('private_key', null);
        $this->setSetting('public_key', null);
        $this->setSetting('status', 'disconnected');
        $this->setSetting('enabled', 'false');
        $this->setSetting('last_error', null);
        $this->setSetting('heartbeat_endpoint', null);
        $this->setSetting('heartbeat_interval', null);
    }
}
