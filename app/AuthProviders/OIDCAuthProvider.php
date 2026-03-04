<?php

namespace App\AuthProviders;

use App\Models\AuthProvider as AuthProviderModel;
use Jumbojett\OpenIDConnectClient;
use App\AuthProviders\AuthProviderUser;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;
use App\AuthProviders\overrides\ErugoOpenIDConnectclient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;

class OIDCAuthProvider extends BaseAuthProvider
{

  protected $client_id;
  protected $client_secret;
  protected $base_url;
  protected $provider;

  public function __construct(AuthProviderModel $provider)
  {
    $this->client_id = $provider->provider_config->client_id;
    $this->client_secret = $provider->provider_config->client_secret;
    $this->base_url = $provider->provider_config->base_url;
    $this->provider = $provider;
  }

  private function getAdvancedConfig($key, $default = '')
  {
    return $this->provider->provider_config->$key ?? $default;
  }

  private function createClient()
  {

    //let's check we have all the required data
    if (!$this->client_id || !$this->client_secret || !$this->base_url) {
      $this->throwMissingDataException();
    }

    $client =  new ErugoOpenIDConnectclient(
      $this->base_url,
      $this->client_id,
      $this->client_secret
    );

    // Apply custom endpoint overrides if configured
    $endpointOverrides = [];
    $endpointKeys = [
      'authorization_endpoint',
      'token_endpoint',
      'userinfo_endpoint',
      'end_session_endpoint',
    ];

    foreach ($endpointKeys as $key) {
      $value = $this->getAdvancedConfig($key);
      if (!empty($value)) {
        $endpointOverrides[$key] = $value;
      }
    }

    if (!empty($endpointOverrides)) {
      $client->providerConfigParam($endpointOverrides);
    }

    // Set callback URL and required scopes
    $route = route('social.provider.callback', ['provider' => $this->provider->uuid]);
    $client->setRedirectURL($route);

    // Use custom scopes if configured, otherwise use defaults
    $customScopes = $this->getAdvancedConfig('scopes');
    if (!empty($customScopes)) {
      $client->addScope(array_map('trim', explode(' ', $customScopes)));
    } else {
      $client->addScope(['openid', 'email', 'profile']);
    }

    return $client;
  }

  public function redirect()
  {
    // Store the linking data in an encrypted cookie
    $linkingData = [
      'linkingAccount' => session('linkingAccount'),
      'linkingUserId' => session('linkingUserId')
    ];
    session_start();
    $_SESSION['oidc_linking_data'] = $linkingData;


    // Create OIDC client
    $oidc = $this->createClient();

    // Begin authentication flow
    $oidc->authenticate();

    $this->throwAuthFailureException();
  }

  public function handleCallback(): AuthProviderUser
  {
    session_start();
    $linkingData = $_SESSION['oidc_linking_data'];

    \Log::info("Linking data: " . json_encode($linkingData));

    if (isset($linkingData['linkingAccount']) && isset($linkingData['linkingUserId'])) {
      session(['linkingAccount' => $linkingData['linkingAccount']]);
      session(['linkingUserId' => $linkingData['linkingUserId']]);
    }

    // Create OIDC client and complete authentication
    $oidc = $this->createClient();
    $oidc->authenticate();

    // Get verified claims from the ID token (always available after authenticate)
    $idTokenClaims = $oidc->getVerifiedClaims();

    \Log::info("ID token claims: " . json_encode($idTokenClaims));

    // Try to get user info from userinfo endpoint, but don't fail if it errors
    // Some providers (e.g. ADFS) don't support the userinfo endpoint properly
    // and return all claims in the ID token instead
    $userInfo = null;
    try {
      $userInfo = $oidc->requestUserInfo();
      \Log::info("User info: " . json_encode($userInfo));
    } catch (\Exception $e) {
      \Log::warning("Userinfo endpoint failed, using ID token claims only: " . $e->getMessage());
    }

    // Resolve claim mappings (custom or default)
    $claimSub = $this->getAdvancedConfig('claim_sub', 'sub');
    $claimName = $this->getAdvancedConfig('claim_name', 'name');
    $claimEmail = $this->getAdvancedConfig('claim_email', 'email');
    $claimAvatar = $this->getAdvancedConfig('claim_avatar', 'picture');
    $claimVerified = $this->getAdvancedConfig('claim_email_verified', 'email_verified');

    // Use empty string as fallback indicator for default claim names
    if (empty($claimSub)) $claimSub = 'sub';
    if (empty($claimName)) $claimName = 'name';
    if (empty($claimEmail)) $claimEmail = 'email';
    if (empty($claimAvatar)) $claimAvatar = 'picture';
    if (empty($claimVerified)) $claimVerified = 'email_verified';

    // Helper to resolve a claim value from userinfo first, then ID token as fallback
    $resolveClaim = function ($claimKey) use ($userInfo, $idTokenClaims) {
      if (isset($userInfo->$claimKey)) {
        return $userInfo->$claimKey;
      }
      if (isset($idTokenClaims->$claimKey)) {
        return $idTokenClaims->$claimKey;
      }
      return null;
    };

    $userdata = [
      'sub' => $resolveClaim($claimSub),
      'name' => $resolveClaim($claimName),
      'email' => $resolveClaim($claimEmail),
      'avatar' => $resolveClaim($claimAvatar),
      'verified' => $resolveClaim($claimVerified) ?? false
    ];

    \Log::info("User data: " . json_encode($userdata));

    return new AuthProviderUser($userdata);
  }


  public static function getIcon(): string
  {
    return '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 120 120"><path d="m 75.180374,15.11293 -15.99577,7.797938 0,79.945522 C 40.931432,100.568 27.193065,90.619126 27.193065,78.662788 c 0,-11.334002 12.358733,-20.879977 29.192279,-23.793706 l 0,-10.163979 C 30.637155,47.81728 11.197296,61.839238 11.197296,78.662788 c 0,17.429891 20.856984,31.825422 47.987308,34.224282 l 15.99577,-7.53134 0,-90.2428 z m 2.79926,29.592173 0,10.163979 c 6.261409,1.083679 11.913385,3.061436 16.528961,5.731817 l -8.664375,4.898704 30.95849,6.731553 -2.23275,-22.927269 -8.23115,4.632108 C 98.692362,49.310409 88.899095,46.024898 77.979634,44.705103 z" /></svg>';
  }

  public static function getName(): string
  {
    return 'OpenID Connect';
  }

  public static function getDescription(): string
  {
    return 'OpenID Connect is a standard for authentication and authorization that allows users to sign in to your application using their Google, Microsoft, or other OpenID Connect-compatible accounts.';
  }

  public static function getValidator(array $data): Validator
  {
    return ValidatorFacade::make($data, [
      'client_id' => ['required', 'string'],
      'client_secret' => ['required', 'string'],
      'base_url' => ['required', 'url'],
      'authorization_endpoint' => ['nullable', 'url'],
      'token_endpoint' => ['nullable', 'url'],
      'userinfo_endpoint' => ['nullable', 'url'],
      'end_session_endpoint' => ['nullable', 'url'],
      'scopes' => ['nullable', 'string'],
      'claim_sub' => ['nullable', 'string'],
      'claim_name' => ['nullable', 'string'],
      'claim_email' => ['nullable', 'string'],
      'claim_avatar' => ['nullable', 'string'],
      'claim_email_verified' => ['nullable', 'string'],
    ]);
  }

  public static function getInformationUrl(): ?string
  {
    return 'https://openid.net/connect/';
  }

  public static function getEmptyProviderConfig(): array
  {
    return [
      'client_id' => '',
      'client_secret' => '',
      'base_url' => '',
      'authorization_endpoint' => '',
      'token_endpoint' => '',
      'userinfo_endpoint' => '',
      'end_session_endpoint' => '',
      'scopes' => '',
      'claim_sub' => '',
      'claim_name' => '',
      'claim_email' => '',
      'claim_avatar' => '',
      'claim_email_verified' => '',
    ];
  }

  public static function getAdvancedProviderConfig(): array
  {
    return [
      'authorization_endpoint' => '',
      'token_endpoint' => '',
      'userinfo_endpoint' => '',
      'end_session_endpoint' => '',
      'scopes' => '',
      'claim_sub' => '',
      'claim_name' => '',
      'claim_email' => '',
      'claim_avatar' => '',
      'claim_email_verified' => '',
    ];
  }

  public static function getAdvancedConfigKeys(): array
  {
    return [
      'authorization_endpoint',
      'token_endpoint',
      'userinfo_endpoint',
      'end_session_endpoint',
      'scopes',
      'claim_sub',
      'claim_name',
      'claim_email',
      'claim_avatar',
      'claim_email_verified',
    ];
  }
}
