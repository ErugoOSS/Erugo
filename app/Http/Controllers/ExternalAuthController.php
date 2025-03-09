<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\AuthProviders\AuthProviderUser;
use Illuminate\Support\Facades\Auth;
use App\Services\SettingsService;
use App\Models\AuthProvider;
use App\Models\UserAuthProvider;
use Jumbojett\OpenIDConnectClient;

/**
 * Controller for handling social authentication methods
 * Supports Google OAuth and OpenID Connect (OIDC)
 */
class ExternalAuthController extends Controller
{
    /**
     * Settings service for retrieving OAuth configuration
     *
     * @var SettingsService
     */
    private $settings;

    /**
     * Get the fully qualified class name for an auth provider
     *
     * @param string $class The provider class name without namespace
     * @return string The fully qualified class name
     */
    private function getProviderClass($class)
    {
        return "App\\AuthProviders\\" . $class . "AuthProvider";
    }

    /**
     * Initialize the controller
     */
    public function __construct()
    {
        // Force HTTPS for all redirect URLs for security
        URL::forceScheme('https');

        // Inject the settings service
        $this->settings = app()->make(SettingsService::class);
    }

    /**
     * Redirect the user to the authentication provider's login page.
     *
     * @param int $providerId The ID of the authentication provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function providerLogin($providerId)
    {
        // Retrieve the authentication provider by ID
        $provider = $this->getProviderById($providerId);

        if (!$provider) {
            return redirect('/')->with('error', 'Provider not found');
        }

        // Instantiate the provider class and redirect to the provider's login page
        $providerClass = $this->instantiateProviderClass($provider);
        return $providerClass->redirect();
    }

    /**
     * Redirect the user to the authentication provider's login page for account linking.
     *
     * @param int $providerId The ID of the authentication provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function linkAccount($providerId)
    {
        // Validate user is authenticated
        $user = $this->getUserFromRefreshToken();
        if (!$user) {
            return redirect('/')->with('error', 'Authentication failed. Please try again.');
        }

        // Retrieve the authentication provider by ID
        $provider = $this->getProviderById($providerId);
        if (!$provider) {
            return redirect('/')->with('error', 'Provider not found');
        }

        // Set session variables for the linking process
        $this->setLinkingSessionVariables($user->id);

        // Instantiate the provider class and redirect to the provider's login page
        $providerClass = $this->instantiateProviderClass($provider);
        return $providerClass->redirect();
    }

    /**
     * Handle the callback from the authentication provider.
     *
     * @param string $providerUuid The UUID of the authentication provider
     * @return \Illuminate\Http\RedirectResponse
     */
    public function providerCallback($providerUuid)
    {
        // Retrieve the authentication provider by UUID
        $provider = AuthProvider::where('uuid', $providerUuid)->first();
        if (!$provider) {
            return redirect('/')->with('error', 'Provider not found');
        }

        // Get user information from the provider
        $authProviderUser = $this->getAuthProviderUser($provider);

        // Check if we're linking an account
        $isLinking = session('linkingAccount', false);
        $linkingUserId = session('linkingUserId', null);

        if ($isLinking && $linkingUserId) {
            return $this->handleAccountLinking($provider, $authProviderUser, $linkingUserId);
        }

        // Normal login flow
        return $this->handleNormalLogin($provider, $authProviderUser);
    }

    /**
     * Get provider by ID
     *
     * @param int $providerId The provider ID
     * @return AuthProvider|null
     */
    private function getProviderById($providerId)
    {
        return AuthProvider::where('id', $providerId)->first();
    }

    /**
     * Instantiate the provider class
     *
     * @param AuthProvider $provider The provider model
     * @return object The provider class instance
     */
    private function instantiateProviderClass($provider)
    {
        $class = $this->getProviderClass($provider->provider_class);
        return new $class($provider);
    }

    /**
     * Get user from refresh token in cookie
     *
     * @return User|null
     */
    private function getUserFromRefreshToken()
    {
        $refreshToken = request()->cookie('refresh_token');
        if (!$refreshToken) {
            return null;
        }

        return Auth::setToken(urldecode($refreshToken))->user();
    }

    /**
     * Set session variables for account linking
     *
     * @param int $userId The user ID to link with
     * @return void
     */
    private function setLinkingSessionVariables($userId)
    {
        session(['linkingAccount' => true]);
        session(['linkingUserId' => $userId]);
    }

    /**
     * Get user information from the auth provider
     *
     * @param AuthProvider $provider The provider model
     * @return AuthProviderUser
     */
    private function getAuthProviderUser($provider)
    {
        $class = $this->getProviderClass($provider->provider_class);
        $providerClass = new $class($provider);
        return $providerClass->handleCallback();
    }

    /**
     * Handle account linking process
     *
     * @param AuthProvider $provider The provider model
     * @param AuthProviderUser $authProviderUser The user from the provider
     * @param int $linkingUserId The ID of the user to link with
     * @return \Illuminate\Http\RedirectResponse
     */
    private function handleAccountLinking($provider, $authProviderUser, $linkingUserId)
    {
        // Find the user we're linking to
        $user = User::find($linkingUserId);

        if (!$user) {
            return redirect('/')->with('error', 'User not found');
        }

        // Link the provider to the user
        $this->linkProviderToUser($user, $provider, $authProviderUser);

        // Clear the linking session variables
        session()->forget(['linkingAccount', 'linkingUserId']);

        // Auth as the user automatically
        Auth::login($user);

        return redirect('/')->with('success', 'Account linked successfully');
    }

    /**
     * Handle normal login flow
     *
     * @param AuthProvider $provider The provider model
     * @param AuthProviderUser $authProviderUser The user from the provider
     * @return \Illuminate\Http\RedirectResponse
     */
    private function handleNormalLogin($provider, $authProviderUser)
    {
        // Check if the provider is linked to any user
        $linkedAuth = UserAuthProvider::where('auth_provider_id', $provider->id)
            ->where('provider_user_id', $authProviderUser->sub)
            ->first();

        if ($linkedAuth) {
            return $this->loginWithLinkedAccount($linkedAuth);
        }

        // No linked account found, try to find a user with the same email
        return $this->loginWithEmail($provider, $authProviderUser);
    }

    /**
     * Login with a linked account
     *
     * @param UserAuthProvider $linkedAuth The linked auth provider record
     * @return \Illuminate\Http\RedirectResponse
     */
    private function loginWithLinkedAccount($linkedAuth)
    {
        $user = User::find($linkedAuth->user_id);

        if (!$user) {
            return redirect('/')->with('error', 'User not found');
        }

        return $this->authenticateAndRedirect($user);
    }

    /**
     * Login with email (and link account if found)
     *
     * @param AuthProvider $provider The provider model
     * @param AuthProviderUser $authProviderUser The user from the provider
     * @return \Illuminate\Http\RedirectResponse
     */
    private function loginWithEmail($provider, $authProviderUser)
    {
        if (!$provider->trust_email) {
            return redirect('/')->with('error', 'Account not found. Please check that you have linked your account to this provider.');
        }

        $user = User::where('email', $authProviderUser->email)->first();

        if (!$user) {
            return redirect('/')->with('error', 'Account not found. Please check that you have linked your account to this provider.');
        }

        // Create JWT token
        $token = Auth::login($user);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        // Link the provider to the user since we found them by email
        $this->linkProviderToUser($user, $provider, $authProviderUser);

        return $this->createAuthCookieAndRedirect($user);
    }

    /**
     * Link a provider to a user
     *
     * @param User $user The user model
     * @param AuthProvider $provider The provider model
     * @param AuthProviderUser $authProviderUser The user from the provider
     * @return void
     */
    private function linkProviderToUser($user, $provider, $authProviderUser)
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

    /**
     * Authenticate user and redirect
     *
     * @param User $user The user to authenticate
     * @return \Illuminate\Http\RedirectResponse
     */
    private function authenticateAndRedirect($user)
    {
        // Create JWT token
        $token = Auth::login($user);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        return $this->createAuthCookieAndRedirect($user);
    }

    /**
     * Create auth cookie and redirect
     *
     * @param User $user The authenticated user
     * @return \Illuminate\Http\RedirectResponse
     */
    private function createAuthCookieAndRedirect($user)
    {
        // Set refresh token with 24 hour expiry
        $twentyFourHours = 60 * 60 * 24;
        $refreshToken = Auth::setTTL($twentyFourHours)->tokenById($user->id);

        // Create HTTP-only secure cookie with refresh token
        $cookie = cookie('refresh_token', urlencode($refreshToken), $twentyFourHours, null, null, true, true);

        // Redirect to home with the cookie
        return redirect('/')->withCookie($cookie);
    }
}
