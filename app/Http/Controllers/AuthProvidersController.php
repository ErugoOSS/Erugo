<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuthProvider;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;

/**
 * Controller responsible for managing authentication providers
 * 
 * This controller handles CRUD operations for authentication providers
 * such as OAuth, SAML, OpenID Connect, etc. It includes both admin-only endpoints
 * and public-facing endpoints.
 */
class AuthProvidersController extends Controller
{



  public function __construct()
  {
    URL::forceScheme('https');
  }

  /**
   * Retrieve all authentication providers with complete information
   * 
   * This admin-only endpoint returns all details about each auth provider,
   * including configuration and metadata from the provider class.
   * 
   * @return \Illuminate\Http\JsonResponse JSON response with all auth providers
   */
  public function index()
  {
    // Fetch all auth providers from the database
    $authProviders = AuthProvider::all();

    // Transform each provider to include details from its provider class
    $authProviders = $authProviders->map(function ($authProvider) {
      // Get the fully qualified provider class name
      $class = $this->getProviderClass($authProvider->provider_class);
      //does the class exist?
      if (!$this->classExists($class)) {
        return null;
      }

      // Return a formatted array with provider details
      return [
        'id' => $authProvider->id,
        'name' => $authProvider->name,
        'provider_name' => $class::getName(),
        'provider_description' => $class::getDescription(),
        'enabled' => $authProvider->enabled == "true",
        'class' => $authProvider->provider_class,
        'provider_config' => $authProvider->provider_config,
        'icon' => $class::getIcon(),
        'information_url' => $class::getInformationUrl(),
        'uuid' => $authProvider->uuid,
        'callback_url' => route('social.provider.callback', ['provider' => $authProvider->uuid])
      ];
    });

    //remove nulls
    $authProviders = $authProviders->filter(function ($authProvider) {
      return $authProvider !== null;
    });

    //convert back to array
    $authProvidersArray = [];
    foreach ($authProviders as $authProvider) {
      $authProvidersArray[] = $authProvider;
    }


    // Return JSON response with success status and data
    return response()->json(
      [
        'status' => 'success',
        'message' => 'Auth providers fetched successfully',
        'data' => [
          'authProviders' => $authProvidersArray
        ]
      ]
    );
  }

  /**
   * Get the callback URL for a specific authentication provider by UUID
   * 
   * This endpoint returns the callback URL for a given auth provider UUID.
   * 
   * @param string $uuid UUID of the auth provider
   * @return \Illuminate\Http\JsonResponse JSON response with the callback URL
   */
  public function getCallbackUrl($uuid)
  {
    return response()->json([
      'status' => 'success',
      'message' => 'Callback URL fetched successfully',
      'data' => [
        'callbackUrl' => route('social.provider.callback', ['provider' => $uuid])
      ]
    ]);
  }

  /**
   * Convert short class name to fully qualified class name
   * 
   * @param string $class Short class name
   * @return string Fully qualified class name
   */
  private function getProviderClass($class)
  {
    return "App\\AuthProviders\\" . $class . "AuthProvider";
  }

  /**
   * Update multiple authentication providers in a single request
   * 
   * This admin-only endpoint allows bulk updating of provider configurations.
   * It validates each provider before saving any changes.
   * 
   * @param Request $request HTTP request containing provider data
   * @return \Illuminate\Http\JsonResponse Status of the update operation
   */
  public function bulkUpdate(Request $request)
  {
    // Get providers array from request
    $providers = $request->providers;
    // Create an empty message bag for validation errors
    $errorBag = new MessageBag();
    // Array to store valid providers
    $okToSave = [];

    // Validate each provider
    foreach ($providers as $provider) {
      if ($this->validateProvider($provider, $errorBag)) {
        $okToSave[] = $provider;
      }
    }

    // If there are validation errors, return error response
    if ($errorBag->isNotEmpty()) {
      return response()->json([
        'status' => 'error',
        'message' => 'Invalid provider config',
        'data' => [
          'errors' => $errorBag
        ]
      ], 422);
    }

    // Try to save all valid providers
    try {
      foreach ($okToSave as $provider) {
        $this->saveProvider($provider);
      }
    } catch (\Exception $e) {
      // If any save operation fails, return error response
      return response()->json([
        'status' => 'error',
        'message' => 'Error saving provider',
        'data' => [
          'error' => $e->getMessage()
        ]
      ], 500);
    }

    // Return success response if all providers were saved
    return response()->json([
      'status' => 'success',
      'message' => 'Providers saved successfully'
    ]);
  }

  /**
   * Save a single provider to the database
   * 
   * @param array $provider Provider data to save
   * @return AuthProvider Updated auth provider model
   * @throws \Exception If provider not found
   */
  private function saveProvider($provider)
  {
    // Try to find existing provider by ID
    $authProvider = isset($provider['id'])
      ? AuthProvider::find($provider['id'])
      : null;

    // Create new provider if it doesn't exist
    if (!$authProvider) {
      $authProvider = new AuthProvider();
      $authProvider->name = $provider['name'];
      $authProvider->provider_class = $provider['class'];
      $authProvider->provider_config = $provider['provider_config'];
      $authProvider->enabled = $provider['enabled'];
      $authProvider->uuid = $provider['uuid'];
      $authProvider->save();

      return $authProvider;
    }

    // Update existing provider
    $authProvider->update([
      'name' => $provider['name'],
      'provider_config' => $provider['provider_config'],
      'enabled' => $provider['enabled']
    ]);

    return $authProvider;
  }

  /**
   * Validate a provider's configuration and add errors to error bag if invalid
   * 
   * This method validates both the base details (name, etc.) and the
   * provider-specific configuration.
   * 
   * @param array $provider Provider data to validate
   * @param MessageBag $errorBag Error bag to add validation errors to
   * @return bool True if provider is valid, false otherwise
   */
  private function validateProvider(array $provider, MessageBag $errorBag): bool
  {
    // Get the provider class to use its validator
    $class = $this->getProviderClass($provider['class']);
    //does the class exist?
    if (!$this->classExists($class)) {
      $errorBag->add($provider['id'], 'Provider class does not exist');
      return false;
    }
    // Validate provider-specific configuration
    $providerConfigValidator = $class::getValidator($provider['provider_config']);

    // Validate base details
    $baseDetailsValidator = Validator::make($provider, [
      'name' => ['required', 'string', 'max:255'],
      'uuid' => ['required', 'uuid']
    ]);

    $isValid = true;

    if (!isset($provider['id'])) {
      $provider['id'] = 'new-provider';
    }

    // Check if provider config is valid
    if ($providerConfigValidator->fails()) {
      $errorBag->add($provider['id'], $providerConfigValidator->errors());
      $isValid = false;
    }

    // Check if base details are valid
    if ($baseDetailsValidator->fails()) {
      $errorBag->add($provider['id'], $baseDetailsValidator->errors());
      $isValid = false;
    }

    return $isValid;
  }


  /**
   * Delete an authentication provider by its ID
   * 
   * This admin-only endpoint deletes the specified authentication provider
   * from the database.
   * 
   * @param int $id ID of the authentication provider to delete
   * @return \Illuminate\Http\JsonResponse JSON response with the result of the deletion
   */
  public function delete($id)
  {
    // Find the auth provider by ID
    $authProvider = AuthProvider::find($id);

    // Check if the auth provider exists
    if (!$authProvider) {
      return response()->json(
        [
          'status' => 'error',
          'message' => 'Auth provider not found'
        ],
        404
      );
    }

    // Delete the auth provider
    $authProvider->delete();

    // Return JSON response with success status
    return response()->json(
      [
        'status' => 'success',
        'message' => 'Auth provider deleted successfully'
      ]
    );
  }

  /**
   * Retrieve a list of available provider types
   * 
   * This method scans the AuthProviders namespace for classes that follow the
   * naming convention of XXXAuthProvider and returns their names.
   * 
   * @return \Illuminate\Http\JsonResponse JSON response with available provider types
   */
  public function listAvailableProviderTypes()
  {
    $namespace = 'App\\AuthProviders';
    $path = app_path('AuthProviders');
    $providerTypes = [];

    foreach (scandir($path) as $file) {
      if (preg_match('/^(\w+)AuthProvider\.php$/', $file, $matches)) {
        if (!in_array($matches[1], ['Base'])) {
          $providerTypes[] = $matches[1];
        }
      }
    }

    $providers = [];
    foreach ($providerTypes as $providerType) {
      $class = $this->getProviderClass($providerType);
      //does the class exist?
      if (!$this->classExists($class)) {
        continue;
      }
      $providers[] = [
        'name' => $class::getName(),
        'description' => $class::getDescription(),
        'icon' => $class::getIcon(),
        'class' => $providerType,
        'provider_config' => $class::getEmptyProviderConfig()
      ];
    }
    return response()->json(
      [
        'status' => 'success',
        'message' => 'Available provider types fetched successfully',
        'data' => [
          'providers' => $providers
        ]
      ]
    );
  }



  /**
   * Retrieve a list of auth providers with minimal information
   * 
   * This public endpoint returns only the essential information about
   * enabled auth providers (ID, name, and icon) for use in login pages.
   * 
   * @return \Illuminate\Http\JsonResponse JSON response with simplified auth providers list
   */
  public function list()
  {
    // Fetch all auth providers from the database
    $authProviders = AuthProvider::where('enabled', true)->get();

    // Transform each provider to include only necessary public information
    $authProviders = $authProviders->map(function ($authProvider) {
      $class = $this->getProviderClass($authProvider->provider_class);
      //does the class exist?
      if (!$this->classExists($class)) {
        return null;
      }
      $icon = $class::getIcon();
      return [
        'id' => $authProvider->id,
        'name' => $authProvider->name,
        'icon' => $icon
      ];
    });

    //remove nulls
    $authProviders = $authProviders->filter(function ($authProvider) {
      return $authProvider !== null;
    });

    //convert back to array
    $authProvidersArray = [];
    foreach ($authProviders as $authProvider) {
      $authProvidersArray[] = $authProvider;
    }
    // Return JSON response with success status and data
    return response()->json(
      [
        'status' => 'success',
        'message' => 'Auth providers fetched successfully',
        'data' => [
          'authProviders' => $authProvidersArray
        ]
      ]
    );
  }

  private function classExists($class)
  {
    //can we find the file?
    $classWithoutNamespace = str_replace('App\\AuthProviders\\', '', $class);
    $path = app_path('AuthProviders/' . $classWithoutNamespace . '.php');
    if (!file_exists($path)) {
      return false;
    }
    //does the class exist?
    if (!class_exists($class)) {
      return false;
    }
    return true;
  }
}
