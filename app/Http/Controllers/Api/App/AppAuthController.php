<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use App\Models\ReverseShareInvite;
use App\Models\AuthProvider;
use App\Models\AppAuthState;
use App\Models\UserAuthProvider;
use App\Mail\passwordResetMail;
use App\Mail\emailVerificationMail;
use App\Jobs\sendEmail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules\Password as PasswordRules;
use Carbon\Carbon;

class AppAuthController extends Controller
{
    /**
     * Access token TTL in minutes (default from JWT config)
     */
    private function getAccessTokenTTL(): int
    {
        return Auth::factory()->getTTL();
    }

    /**
     * Refresh token TTL in minutes (30 days for apps)
     */
    private function getRefreshTokenTTL(): int
    {
        return 60 * 24 * 30; // 30 days
    }

    /**
     * Login with email and password
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_INVALID_CREDENTIALS',
                'message' => 'Invalid email or password'
            ], 401);
        }

        $user = Auth::user();

        if (!$user->active) {
            Auth::logout();
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_ACCOUNT_INACTIVE',
                'message' => 'Account is not active'
            ], 401);
        }

        return $this->respondWithTokens($user, $request->input('device_name'));
    }

    /**
     * Refresh the access token using a refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        $refreshToken = $request->input('refresh_token');
        
        try {
            $user = Auth::setToken($refreshToken)->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'AUTH_TOKEN_INVALID',
                    'message' => 'Invalid refresh token'
                ], 401);
            }

            if (!$user->active) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'AUTH_ACCOUNT_INACTIVE',
                    'message' => 'Account is not active'
                ], 401);
            }

            // Invalidate old token and generate new ones
            Auth::invalidate();
            
            return $this->respondWithTokens($user, null, 'Token refreshed');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_TOKEN_INVALID',
                'message' => 'Invalid or expired refresh token'
            ], 401);
        }
    }

    /**
     * Logout and invalidate tokens
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            Auth::logout();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            // Even if token is invalid, return success
            return response()->json([
                'status' => 'success',
                'message' => 'Logged out successfully'
            ]);
        }
    }

    /**
     * Request a password reset email
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Always return success to prevent email enumeration
        if (!$user) {
            return response()->json([
                'status' => 'success',
                'message' => 'If an account exists with this email, a password reset link has been sent'
            ]);
        }

        $token = Password::createToken($user);
        sendEmail::dispatch($user->email, passwordResetMail::class, ['token' => $token, 'user' => $user]);

        return response()->json([
            'status' => 'success',
            'message' => 'If an account exists with this email, a password reset link has been sent'
        ]);
    }

    /**
     * Reset password with token and return new auth tokens
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            $user = User::where('email', $request->email)->first();
            return $this->respondWithTokens($user);
        }

        return response()->json([
            'status' => 'error',
            'code' => 'AUTH_RESET_FAILED',
            'message' => 'Password reset failed. The token may be invalid or expired.'
        ], 400);
    }

    /**
     * Register a new user via self-registration
     */
    public function register(Request $request): JsonResponse
    {
        // Check if self-registration is enabled
        $enabled = Setting::where('key', 'self_registration_enabled')->first();
        if (!$enabled || $enabled->value !== 'true') {
            return response()->json([
                'status' => 'error',
                'code' => 'REGISTRATION_DISABLED',
                'message' => 'Self-registration is not enabled'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRules::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        // Check domain restrictions
        $allowAnyDomain = Setting::where('key', 'self_registration_allow_any_domain')->first();
        $allowedDomains = Setting::where('key', 'self_registration_allowed_domains')->first();

        if (!$allowAnyDomain || $allowAnyDomain->value !== 'true') {
            $email = $request->email;
            $domain = strtolower(substr(strrchr($email, "@"), 1));
            
            $domains = [];
            if ($allowedDomains && !empty($allowedDomains->value)) {
                $domains = array_map('trim', array_map('strtolower', explode(',', $allowedDomains->value)));
            }

            if (empty($domains) || !in_array($domain, $domains)) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'REGISTRATION_DOMAIN_NOT_ALLOWED',
                    'message' => 'Registration is not allowed for this email domain'
                ], 403);
            }
        }

        // Generate 6-digit verification code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(15);

        try {
            $user = User::create([
                'email' => $request->email,
                'name' => $request->name,
                'password' => Hash::make($request->password),
                'admin' => false,
                'active' => false,
                'must_change_password' => false,
                'email_verification_code' => $code,
                'email_verification_code_expires_at' => $expiresAt,
            ]);

            // Migrate any existing reverse share invites
            try {
                $existingInvites = ReverseShareInvite::where('recipient_email', $user->email)->get();
                foreach ($existingInvites as $invite) {
                    if ($invite->guestUser && $invite->guestUser->is_guest) {
                        $invite->guestUser->delete();
                    }
                    $invite->guest_user_id = $user->id;
                    $invite->save();
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to migrate reverse share invites for user ' . $user->email . ': ' . $e->getMessage());
            }

            // Send verification email
            sendEmail::dispatch($user->email, emailVerificationMail::class, ['code' => $code, 'user' => $user]);

            return response()->json([
                'status' => 'success',
                'message' => 'Registration successful. Please check your email for the verification code.',
                'data' => [
                    'email' => $user->email
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'REGISTRATION_FAILED',
                'message' => 'Failed to create account'
            ], 500);
        }
    }

    /**
     * Verify email with the 6-digit code and return auth tokens
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_VERIFICATION_FAILED',
                'message' => 'Invalid email or verification code'
            ], 400);
        }

        if ($user->active) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_ALREADY_VERIFIED',
                'message' => 'Account is already verified'
            ], 400);
        }

        if ($user->email_verification_code !== $request->code) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_VERIFICATION_FAILED',
                'message' => 'Invalid email or verification code'
            ], 400);
        }

        if (Carbon::now()->isAfter($user->email_verification_code_expires_at)) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_VERIFICATION_EXPIRED',
                'message' => 'Verification code has expired. Please request a new one.'
            ], 400);
        }

        // Activate the account
        $user->active = true;
        $user->email_verification_code = null;
        $user->email_verification_code_expires_at = null;
        $user->email_verified_at = Carbon::now();
        $user->save();

        // Return tokens so the user is logged in after verification
        return $this->respondWithTokens($user);
    }

    /**
     * Resend verification code
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        // Always return success to prevent email enumeration
        if (!$user || $user->active) {
            return response()->json([
                'status' => 'success',
                'message' => 'If an unverified account exists with this email, a new verification code has been sent.'
            ]);
        }

        // Generate new code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(15);

        $user->email_verification_code = $code;
        $user->email_verification_code_expires_at = $expiresAt;
        $user->save();

        sendEmail::dispatch($user->email, emailVerificationMail::class, ['code' => $code, 'user' => $user]);

        return response()->json([
            'status' => 'success',
            'message' => 'If an unverified account exists with this email, a new verification code has been sent.'
        ]);
    }

    /**
     * Get self-registration settings
     */
    public function registrationSettings(): JsonResponse
    {
        $enabled = Setting::where('key', 'self_registration_enabled')->first();
        $allowAnyDomain = Setting::where('key', 'self_registration_allow_any_domain')->first();
        $allowedDomains = Setting::where('key', 'self_registration_allowed_domains')->first();
        
        // Parse allowed domains from comma-separated string
        $domains = [];
        if ($allowedDomains && !empty($allowedDomains->value)) {
            $domains = array_map('trim', explode(',', $allowedDomains->value));
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'self_registration_enabled' => $enabled ? $enabled->value === 'true' : false,
                'allowed_domains' => $domains,
                'allow_any_domain' => $allowAnyDomain ? $allowAnyDomain->value === 'true' : false,
            ]
        ]);
    }

    /**
     * Respond with access and refresh tokens in body (for native apps)
     */
    private function respondWithTokens(User $user, ?string $deviceName = null, string $message = 'Authentication successful'): JsonResponse
    {
        $accessToken = Auth::login($user);

        if (!$accessToken) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_TOKEN_GENERATION_FAILED',
                'message' => 'Failed to generate access token'
            ], 500);
        }

        // Generate refresh token with 30-day TTL
        $refreshToken = Auth::setTTL($this->getRefreshTokenTTL())->tokenById($user->id);

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'access_token_expires_in' => $this->getAccessTokenTTL() * 60, // seconds
                'refresh_token_expires_in' => $this->getRefreshTokenTTL() * 60, // seconds
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'admin' => (bool) $user->admin,
                    'is_guest' => (bool) $user->is_guest,
                    'must_change_password' => (bool) $user->must_change_password,
                ]
            ]
        ]);
    }

    /**
     * Initiate external OAuth flow for native apps
     * 
     * Generates a state token and returns the authorization URL for the provider.
     * The app should open this URL in a browser/webview.
     */
    public function externalInitiate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider_id' => 'required|integer',
            'callback_scheme' => 'required|string|max:50',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        $provider = AuthProvider::where('id', $request->provider_id)
            ->where('enabled', true)
            ->first();

        if (!$provider) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_PROVIDER_NOT_FOUND',
                'message' => 'Authentication provider not found or not enabled'
            ], 404);
        }

        // Clean up expired states
        AppAuthState::cleanupExpired();

        // Generate cryptographic state token
        $state = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes(10);

        // Store the state
        AppAuthState::create([
            'state' => $state,
            'provider_id' => $provider->id,
            'callback_scheme' => $request->callback_scheme,
            'device_name' => $request->device_name,
            'action' => 'login',
            'expires_at' => $expiresAt,
        ]);

        // Build authorization URL using the provider class
        $providerClass = $this->getProviderClass($provider->provider_class);
        $providerInstance = new $providerClass($provider);
        
        // Get the redirect URL with the state parameter
        // We use a special app-callback URL that will redirect to the native app
        $callbackUrl = url('/auth/provider/' . $provider->uuid . '/app-callback');
        $authorizationUrl = $providerInstance->getAuthorizationUrl($state, $callbackUrl);

        return response()->json([
            'status' => 'success',
            'data' => [
                'authorization_url' => $authorizationUrl,
                'state' => $state,
                'expires_in' => 600, // 10 minutes in seconds
            ]
        ]);
    }

    /**
     * Complete external OAuth flow for native apps
     * 
     * Exchanges the authorization code for tokens and creates/finds user.
     */
    public function externalComplete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'state' => 'required|string|size:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        // Look up the state
        $authState = AppAuthState::where('state', $request->state)->first();

        if (!$authState) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_STATE_INVALID',
                'message' => 'Invalid or expired state token'
            ], 400);
        }

        if ($authState->isExpired()) {
            $authState->delete();
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_STATE_EXPIRED',
                'message' => 'State token has expired'
            ], 400);
        }

        $provider = $authState->provider;
        if (!$provider || !$provider->enabled) {
            $authState->delete();
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_PROVIDER_NOT_FOUND',
                'message' => 'Authentication provider not found or not enabled'
            ], 404);
        }

        try {
            // Exchange code for user info using the provider class
            $providerClass = $this->getProviderClass($provider->provider_class);
            $providerInstance = new $providerClass($provider);
            
            $callbackUrl = url('/auth/provider/' . $provider->uuid . '/app-callback');
            $authProviderUser = $providerInstance->exchangeCodeForUser($request->code, $callbackUrl);

            // Handle account linking if this was a link action
            if ($authState->action === 'link' && $authState->user_id) {
                $user = User::find($authState->user_id);
                if (!$user) {
                    $authState->delete();
                    return response()->json([
                        'status' => 'error',
                        'code' => 'AUTH_USER_NOT_FOUND',
                        'message' => 'User not found'
                    ], 404);
                }

                // Link the provider to the user
                $this->linkProviderToUser($user, $provider, $authProviderUser);
                $authState->delete();

                return $this->respondWithTokens($user, $authState->device_name);
            }

            // Normal login flow
            // Check if the provider is linked to any user
            $linkedAuth = UserAuthProvider::where('auth_provider_id', $provider->id)
                ->where('provider_user_id', $authProviderUser->sub)
                ->first();

            if ($linkedAuth) {
                $user = User::find($linkedAuth->user_id);
                if (!$user) {
                    $authState->delete();
                    return response()->json([
                        'status' => 'error',
                        'code' => 'AUTH_USER_NOT_FOUND',
                        'message' => 'User not found'
                    ], 404);
                }

                if (!$user->active) {
                    $authState->delete();
                    return response()->json([
                        'status' => 'error',
                        'code' => 'AUTH_ACCOUNT_INACTIVE',
                        'message' => 'Account is not active'
                    ], 401);
                }

                $authState->delete();
                return $this->respondWithTokens($user, $authState->device_name);
            }

            // No linked account found, try to find a user with the same email
            $user = null;

            if ($provider->trust_email) {
                $user = User::where('email', $authProviderUser->email)->first();
            }

            // If no user found and allow_registration is enabled, create a new user
            if (!$user && $provider->allow_registration) {
                // Check if email is already taken
                $existingUser = User::where('email', $authProviderUser->email)->first();
                if ($existingUser) {
                    $authState->delete();
                    return response()->json([
                        'status' => 'error',
                        'code' => 'AUTH_EMAIL_EXISTS',
                        'message' => 'An account with this email already exists. Please link your account to this provider from your profile settings.'
                    ], 409);
                }
                
                $user = $this->createUserFromProvider($authProviderUser);
            }

            if (!$user) {
                $authState->delete();
                return response()->json([
                    'status' => 'error',
                    'code' => 'AUTH_ACCOUNT_NOT_FOUND',
                    'message' => 'Account not found. Please check that you have linked your account to this provider.'
                ], 404);
            }

            if (!$user->active) {
                $authState->delete();
                return response()->json([
                    'status' => 'error',
                    'code' => 'AUTH_ACCOUNT_INACTIVE',
                    'message' => 'Account is not active'
                ], 401);
            }

            // Link the provider to the user
            $this->linkProviderToUser($user, $provider, $authProviderUser);
            $authState->delete();

            return $this->respondWithTokens($user, $authState->device_name);
        } catch (\Exception $e) {
            $authState->delete();
            \Log::error('External auth complete failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_EXTERNAL_FAILED',
                'message' => 'External authentication failed'
            ], 500);
        }
    }

    /**
     * Initiate external OAuth flow to link a provider to an existing account
     * 
     * Requires authentication. Similar to externalInitiate but marks the state for linking.
     */
    public function externalLink(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider_id' => 'required|integer',
            'callback_scheme' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors()
                ]
            ], 422);
        }

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        $provider = AuthProvider::where('id', $request->provider_id)
            ->where('enabled', true)
            ->first();

        if (!$provider) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_PROVIDER_NOT_FOUND',
                'message' => 'Authentication provider not found or not enabled'
            ], 404);
        }

        // Check if provider is already linked
        $existingLink = $user->authProviders()->where('auth_provider_id', $provider->id)->first();
        if ($existingLink) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_PROVIDER_ALREADY_LINKED',
                'message' => 'This provider is already linked to your account'
            ], 409);
        }

        // Clean up expired states
        AppAuthState::cleanupExpired();

        // Generate cryptographic state token
        $state = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes(10);

        // Store the state with user_id for linking
        AppAuthState::create([
            'state' => $state,
            'provider_id' => $provider->id,
            'callback_scheme' => $request->callback_scheme,
            'action' => 'link',
            'user_id' => $user->id,
            'expires_at' => $expiresAt,
        ]);

        // Build authorization URL using the provider class
        $providerClass = $this->getProviderClass($provider->provider_class);
        $providerInstance = new $providerClass($provider);
        
        $callbackUrl = url('/auth/provider/' . $provider->uuid . '/app-callback');
        $authorizationUrl = $providerInstance->getAuthorizationUrl($state, $callbackUrl);

        return response()->json([
            'status' => 'success',
            'data' => [
                'authorization_url' => $authorizationUrl,
                'state' => $state,
                'expires_in' => 600,
            ]
        ]);
    }

    /**
     * Get the fully qualified class name for an auth provider
     */
    private function getProviderClass(string $class): string
    {
        return "App\\AuthProviders\\" . $class . "AuthProvider";
    }

    /**
     * Create a new user from auth provider data
     */
    private function createUserFromProvider($authProviderUser): User
    {
        $user = User::create([
            'name' => $authProviderUser->name,
            'email' => $authProviderUser->email,
            'password' => bcrypt(bin2hex(random_bytes(32))),
            'admin' => false,
            'active' => true,
            'must_change_password' => false,
            'is_guest' => false
        ]);

        // Migrate any existing reverse share invites
        try {
            $existingInvites = ReverseShareInvite::where('recipient_email', $user->email)->get();
            foreach ($existingInvites as $invite) {
                if ($invite->guestUser && $invite->guestUser->is_guest) {
                    $invite->guestUser->delete();
                }
                $invite->guest_user_id = $user->id;
                $invite->save();
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to migrate reverse share invites for user ' . $user->email . ': ' . $e->getMessage());
        }

        return $user;
    }

    /**
     * Link a provider to a user
     */
    private function linkProviderToUser(User $user, AuthProvider $provider, $authProviderUser): void
    {
        $user->authProviders()->syncWithoutDetaching([
            $provider->id => [
                'provider_user_id' => $authProviderUser->sub,
                'provider_email' => $authProviderUser->email,
                'provider_data' => json_encode([
                    'name' => $authProviderUser->name,
                    'avatar' => $authProviderUser->avatar ?? null,
                ])
            ]
        ]);
    }
}
