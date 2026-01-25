<?php

namespace App\AuthProviders;
use App\Models\AuthProvider as AuthProviderModel;
use App\AuthProviders\AuthProviderUser;
use Illuminate\Validation\Validator;

interface AuthProviderInterface
{
    public function __construct(AuthProviderModel $provider);

    public function redirect();

    public function handleCallback(): AuthProviderUser;

    /**
     * Get the authorization URL for the OAuth flow with explicit state and callback URL
     * Used by the App API for native app OAuth flows
     *
     * @param string $state The state token to include in the authorization URL
     * @param string $callbackUrl The callback URL to redirect to after authorization
     * @return string The authorization URL
     */
    public function getAuthorizationUrl(string $state, string $callbackUrl): string;

    /**
     * Exchange an authorization code for user information
     * Used by the App API for native app OAuth flows
     *
     * @param string $code The authorization code from the OAuth callback
     * @param string $callbackUrl The callback URL used in the authorization request
     * @return AuthProviderUser The user information from the OAuth provider
     */
    public function exchangeCodeForUser(string $code, string $callbackUrl): AuthProviderUser;

    public static function getIcon(): string;

    public static function getName(): string;

    public static function getDescription(): string;

    public static function getValidator(array $data): Validator;

    public static function getEmptyProviderConfig(): array;

    public static function getInformationUrl(): ?string;
}

