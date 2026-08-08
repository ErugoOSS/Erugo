<?php

namespace App\AuthProviders;

use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use App\AuthProviders\AuthProviderUser;
use App\Models\AuthProvider as AuthProviderModel;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

class GoogleAuthProvider extends BaseAuthProvider
{
  protected $client_id;
  protected $client_secret;
  protected $provider;

  public function __construct(AuthProviderModel $provider)
  {
    $this->client_id = $provider->provider_config->client_id;
    $this->client_secret = $provider->provider_config->client_secret;
    $this->provider = $provider;
  }

  private function createClient()
  {
    //let's check we have all the required data
    if (!$this->client_id || !$this->client_secret) {
      $this->throwMissingDataException();
    }

    $socialite = app(\Laravel\Socialite\SocialiteManager::class);
    

    $client = $socialite->buildProvider(GoogleProvider::class, [
      'client_id' => $this->client_id,
      'client_secret' => $this->client_secret,
      'redirect' => route('social.provider.callback', ['provider' => $this->provider->uuid])
    ]);

    return $client;
  }

  public function redirect()
  {
    // Create Google provider
    $googleProvider = $this->createClient();

    // Begin authentication flow - this will redirect the user
    return $googleProvider->redirect();
  }

  public function handleCallback(): AuthProviderUser
  {
    // Create Google provider
    $googleProvider = $this->createClient();

    // Get the user information
    $user = $googleProvider->user();

    // Return the user information as an AuthProviderUser
    return new AuthProviderUser([
      'sub' => $user->id,
      'name' => $user->name,
      'email' => $user->email,
      'avatar' => $user->avatar,
      'verified' => $user->user['email_verified']
    ]);
  }

  /**
   * Get the authorization URL for the OAuth flow with explicit state and callback URL
   * Used by the App API for native app OAuth flows
   */
  public function getAuthorizationUrl(string $state, string $callbackUrl): string
  {
    if (!$this->client_id) {
      $this->throwMissingDataException();
    }

    $params = [
      'client_id' => $this->client_id,
      'redirect_uri' => $callbackUrl,
      'response_type' => 'code',
      'scope' => 'openid email profile',
      'state' => $state,
      'access_type' => 'offline',
      'prompt' => 'consent',
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
  }

  /**
   * Exchange an authorization code for user information
   * Used by the App API for native app OAuth flows
   */
  public function exchangeCodeForUser(string $code, string $callbackUrl): AuthProviderUser
  {
    if (!$this->client_id || !$this->client_secret) {
      $this->throwMissingDataException();
    }

    // Exchange the code for tokens
    $tokenResponse = \Illuminate\Support\Facades\Http::asForm()->post('https://oauth2.googleapis.com/token', [
      'code' => $code,
      'client_id' => $this->client_id,
      'client_secret' => $this->client_secret,
      'redirect_uri' => $callbackUrl,
      'grant_type' => 'authorization_code',
    ]);

    if (!$tokenResponse->successful()) {
      \Log::error("Google token exchange failed: " . $tokenResponse->body());
      $this->throwAuthFailureException();
    }

    $tokenData = $tokenResponse->json();
    $accessToken = $tokenData['access_token'] ?? null;

    if (!$accessToken) {
      \Log::error("Google token exchange returned no access token");
      $this->throwAuthFailureException();
    }

    // Get user info using the access token
    $userResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
      ->get('https://www.googleapis.com/oauth2/v3/userinfo');

    if (!$userResponse->successful()) {
      \Log::error("Google user info request failed: " . $userResponse->body());
      $this->throwAuthFailureException();
    }

    $userInfo = $userResponse->json();

    return new AuthProviderUser([
      'sub' => $userInfo['sub'],
      'name' => $userInfo['name'],
      'email' => $userInfo['email'],
      'avatar' => $userInfo['picture'] ?? null,
      'verified' => $userInfo['email_verified'] ?? false
    ]);
  }

  public static function getIcon(): string
  {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 488 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M488 261.8C488 403.3 391.1 504 248 504 110.8 504 0 393.2 0 256S110.8 8 248 8c66.8 0 123 24.5 166.3 64.9l-67.5 64.9C258.5 52.6 94.3 116.6 94.3 256c0 86.5 69.1 156.6 153.7 156.6 98.2 0 135-70.4 140.8-106.9H248v-85.3h236.1c2.3 12.7 3.9 24.9 3.9 41.4z"/></svg>';
  }

  public static function getName(): string
  {
    return 'Google';
  }

  public static function getDescription(): string
  {
    return 'Google is a popular authentication provider that allows users to sign in to your application using their Google account.';
  }

  public static function getValidator(array $data): Validator
  {
    return ValidatorFacade::make($data, [
      'client_id' => ['required', 'string'],
      'client_secret' => ['required', 'string'],
    ]);
  }

  public static function getInformationUrl(): ?string
  {
    return 'https://developers.google.com/identity/sign-in/web/sign-in';
  }

  public static function getEmptyProviderConfig(): array
  {
    return [
      'client_id' => '',
      'client_secret' => '',
    ];
  }
}
