<?php

namespace App\AuthProviders;

use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Authentik\Provider as AuthentikProvider;
use App\AuthProviders\AuthProviderUser;
use App\Models\AuthProvider as AuthProviderModel;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

class AuthentikAuthProvider extends BaseAuthProvider
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

  private function getConfig(bool $asArray = false)
  {
    if (!$asArray) {
      return new \SocialiteProviders\Manager\Config(
        $this->client_id,
        $this->client_secret,
        route('social.provider.callback', ['provider' => $this->provider->uuid]),
        [
          'base_url' => $this->provider->provider_config->base_url,
        ]
      );
    } else {
      return [
        'client_id' => $this->client_id,
        'client_secret' => $this->client_secret,
        'redirect' => route('social.provider.callback', ['provider' => $this->provider->uuid])
      ];
    }
  }
  public function redirect()
  {
    //let's check we have all the required data
    if (!$this->client_id || !$this->client_secret) {
      $this->throwMissingDataException();
    }

    // Create Authentik provider
    $authentikProvider = Socialite::buildProvider(AuthentikProvider::class, $this->getConfig(true));
    //an aparent bug in socialite, we need to set the config again to get it to see the base_url
    $authentikProvider->setConfig($this->getConfig());

    // Begin authentication flow - this will redirect the user
    return $authentikProvider->redirect();
  }

  public function handleCallback(): AuthProviderUser
  {
    //let's check we have all the required data
    if (!$this->client_id || !$this->client_secret) {
      $this->throwMissingDataException();
    }

    // Create Authentik provider
    $authentikProvider = Socialite::buildProvider(AuthentikProvider::class, $this->getConfig(true));

    //an aparent bug in socialite, we need to set the config again to get it to see the base_url
    $authentikProvider->setConfig($this->getConfig());

    // Get the user information
    $user = $authentikProvider->user();

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

    $baseUrl = rtrim($this->provider->provider_config->base_url, '/');
    
    $params = [
      'client_id' => $this->client_id,
      'redirect_uri' => $callbackUrl,
      'response_type' => 'code',
      'scope' => 'openid email profile',
      'state' => $state,
    ];

    return $baseUrl . '/application/o/authorize/?' . http_build_query($params);
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

    $baseUrl = rtrim($this->provider->provider_config->base_url, '/');

    // Exchange the code for tokens
    $tokenUrl = $baseUrl . '/application/o/token/';
    $tokenResponse = \Illuminate\Support\Facades\Http::asForm()->post($tokenUrl, [
      'code' => $code,
      'client_id' => $this->client_id,
      'client_secret' => $this->client_secret,
      'redirect_uri' => $callbackUrl,
      'grant_type' => 'authorization_code',
    ]);

    if (!$tokenResponse->successful()) {
      \Log::error("Authentik token exchange failed: " . $tokenResponse->body());
      $this->throwAuthFailureException();
    }

    $tokenData = $tokenResponse->json();
    $accessToken = $tokenData['access_token'] ?? null;

    if (!$accessToken) {
      \Log::error("Authentik token exchange returned no access token");
      $this->throwAuthFailureException();
    }

    // Get user info using the access token
    $userResponse = \Illuminate\Support\Facades\Http::withToken($accessToken)
      ->get($baseUrl . '/application/o/userinfo/');

    if (!$userResponse->successful()) {
      \Log::error("Authentik user info request failed: " . $userResponse->body());
      $this->throwAuthFailureException();
    }

    $userInfo = $userResponse->json();

    return new AuthProviderUser([
      'sub' => $userInfo['sub'],
      'name' => $userInfo['name'] ?? $userInfo['preferred_username'],
      'email' => $userInfo['email'],
      'avatar' => $userInfo['picture'] ?? null,
      'verified' => $userInfo['email_verified'] ?? false
    ]);
  }

  public static function getIcon(): string
  {
    return '<?xml version="1.0" encoding="utf-8"?><svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"	 viewBox="0 0 512 512" style="enable-background:new 0 0 512 512;" xml:space="preserve"><g>	<rect x="279.9" y="141" class="st0" width="17.9" height="51.2"/>	<rect x="326.5" y="138.8" class="st0" width="17.9" height="40"/>	<path class="st0" d="M65.3,197.3c-24,0-46,13.2-57.4,34.3h30.4c13.5-11.6,33-15,47.1,0h32.2C105,214.5,86.2,197.3,65.3,197.3z"/>	<path class="st0" d="M108.7,262.4C66.8,350-6.6,275.3,38.3,231.5H7.9c-23.8,41.5,9.1,97.5,57.4,96.3c37.4,0,68.2-55.5,68.2-65.3		c0-4.3-6-17.6-16-31H85.4C96.1,241.2,105.4,255.2,108.7,262.4z M109.8,259.8L109.8,259.8z"/>	<path class="st0" d="M512,140.3v231.3c0,44.3-36.1,80.4-80.4,80.4h-34.1v-78.8h-163v78.8h-34.1c-44.4,0-80.4-36.1-80.4-80.4v-72.8		h258.4V159.8H253.6v78.2H119.9v-97.6c0-3.1,0.2-6.2,0.5-9.2c0.4-3.7,1.1-7.3,2-10.8c0.3-1.1,0.6-2.3,1-3.4c0.1-0.3,0.2-0.6,0.3-0.8		c0.2-0.6,0.4-1.1,0.5-1.7c0.2-0.5,0.4-1.1,0.6-1.7c0.2-0.6,0.5-1.2,0.7-1.8c0.2-0.6,0.5-1.2,0.8-1.8c2-4.7,4.4-9.3,7.3-13.6		c0,0,0.1-0.1,0.1-0.1c0.7-1.1,1.5-2.1,2.3-3.2c0.7-0.9,1.3-1.7,2-2.6c0.8-0.9,1.6-1.9,2.4-2.8c0.8-0.9,1.6-1.8,2.4-2.6l0.1-0.1		c0.4-0.5,0.9-0.9,1.4-1.4c3-2.9,6.2-5.6,9.6-8c0.9-0.7,1.9-1.3,2.8-1.9c1.1-0.7,2.2-1.4,3.3-2c2.1-1.2,4.2-2.4,6.5-3.4		c0.7-0.3,1.4-0.7,2.1-1c3.1-1.3,6.2-2.5,9.4-3.4c1.2-0.4,2.5-0.7,3.7-1c0.6-0.2,1.2-0.3,1.8-0.4c3.6-0.8,7.2-1.3,10.9-1.6l1.6-0.1		c0.3,0,0.5,0,0.8,0c1.2-0.1,2.4-0.1,3.7-0.1h231.3c1.2,0,2.5,0,3.7,0.1c0.3,0,0.5,0,0.8,0l1.6,0.1c3.7,0.3,7.3,0.8,10.9,1.6		c0.6,0.1,1.2,0.3,1.8,0.4c1.3,0.3,2.5,0.6,3.7,1c3.2,0.9,6.3,2.1,9.4,3.4c0.7,0.3,1.4,0.6,2.1,1c2.2,1,4.4,2.2,6.5,3.4		c1.1,0.7,2.2,1.3,3.3,2c1,0.6,1.9,1.3,2.8,1.9c3.9,2.8,7.6,6,11,9.4c0.8,0.8,1.7,1.7,2.4,2.6c0.8,0.9,1.6,1.9,2.4,2.8		c0.7,0.8,1.3,1.7,2,2.6c0.8,1.1,1.5,2.1,2.3,3.2c0,0,0.1,0.1,0.1,0.1c2.9,4.3,5.3,8.8,7.3,13.6c0.2,0.6,0.5,1.2,0.8,1.8		c0.2,0.6,0.5,1.2,0.7,1.8c0.2,0.5,0.4,1.1,0.6,1.7c0.2,0.6,0.4,1.1,0.5,1.7c0.1,0.3,0.2,0.6,0.3,0.8c0.3,1.1,0.7,2.3,1,3.4		c0.9,3.6,1.6,7.2,2,10.8C511.8,134.2,512,137.2,512,140.3z"/>	<path class="st0" d="M498.3,95.5H133.5c14.9-22.2,40-35.6,66.7-35.6h231.3C458.4,59.9,483.4,73.3,498.3,95.5z"/>	<path class="st0" d="M511.5,131.1H120.4c1.4-12.8,6-25,13.1-35.6h364.8C505.5,106.1,510,118.4,511.5,131.1z"/>	<path class="st0" d="M512,140.3v26.4H378.3v-6.9H253.6v6.9H119.9v-26.4c0-3.1,0.2-6.2,0.5-9.2h391.1		C511.8,134.2,512,137.2,512,140.3z"/>	<rect x="119.9" y="166.7" class="st0" width="133.7" height="35.6"/>	<rect x="378.3" y="166.7" class="st0" width="133.7" height="35.6"/>	<rect x="119.9" y="202.3" class="st0" width="133.7" height="35.6"/>	<rect x="378.3" y="202.3" class="st0" width="133.7" height="35.6"/></g></svg>';
  }

  public static function getName(): string
  {
    return 'Authentik';
  }

  public static function getDescription(): string
  {
    return 'Authentik is an open-source authentication provider that allows users to sign in to your application using their Authentik account.';
  }

  public static function getValidator(array $data): Validator
  {
    return ValidatorFacade::make($data, [
      'client_id' => ['required', 'string'],
      'client_secret' => ['required', 'string'],
      'base_url' => ['required', 'url'],
    ]);
  }

  public static function getInformationUrl(): ?string
  {
    return 'https://docs.goauthentik.io/docs/add-secure-apps/applications/manage_apps';
  }

  public static function getEmptyProviderConfig(): array
  {
    return [
      'client_id' => '',
      'client_secret' => '',
      'base_url' => '',
    ];
  }
}
