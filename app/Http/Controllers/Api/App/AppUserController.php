<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRules;

class AppUserController extends Controller
{
    /**
     * Get the current user's profile with linked accounts
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        // Get linked authentication providers
        $linkedAccounts = $user->authProviders->map(function ($provider) {
            return [
                'id' => $provider->id,
                'provider_id' => $provider->id,
                'provider_name' => $provider->name,
                'provider_email' => $provider->pivot->provider_email,
                'linked_at' => $provider->pivot->created_at,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'admin' => (bool) $user->admin,
                    'active' => (bool) $user->active,
                    'must_change_password' => (bool) $user->must_change_password,
                    'linked_accounts' => $linkedAccounts,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
            ]
        ]);
    }

    /**
     * Update the current user's profile (name, email)
     */
    public function update(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $user->id],
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

        try {
            $user->update($validator->validated());

            // Refresh user to get updated timestamps
            $user->refresh();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'admin' => (bool) $user->admin,
                        'active' => (bool) $user->active,
                        'must_change_password' => (bool) $user->must_change_password,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'UPDATE_FAILED',
                'message' => 'Failed to update profile'
            ], 500);
        }
    }

    /**
     * Change the current user's password
     * 
     * This is separate from the profile update for security reasons.
     * Requires current password for verification.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
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

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_INVALID_PASSWORD',
                'message' => 'Current password is incorrect'
            ], 400);
        }

        try {
            $user->password = Hash::make($request->password);
            $user->must_change_password = false;
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'UPDATE_FAILED',
                'message' => 'Failed to change password'
            ], 500);
        }
    }

    /**
     * Unlink an external authentication provider from the current user's account
     */
    public function unlinkProvider($providerId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check if provider exists and is linked to user
        $linkedProvider = $user->authProviders()->where('auth_provider_id', $providerId)->first();
        if (!$linkedProvider) {
            return response()->json([
                'status' => 'error',
                'code' => 'PROVIDER_NOT_LINKED',
                'message' => 'Provider not linked to this account'
            ], 404);
        }

        // Check if this is the only authentication method
        // User needs at least a password OR another linked provider
        $hasPassword = !empty($user->password) && $user->password !== '';
        $providerCount = $user->authProviders()->count();

        if ($providerCount <= 1 && !$hasPassword) {
            return response()->json([
                'status' => 'error',
                'code' => 'CANNOT_UNLINK_ONLY_AUTH',
                'message' => 'Cannot unlink the only authentication method. Please set a password first.'
            ], 400);
        }

        try {
            $user->authProviders()->detach($providerId);

            return response()->json([
                'status' => 'success',
                'message' => 'Provider unlinked successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'UNLINK_FAILED',
                'message' => 'Failed to unlink provider'
            ], 500);
        }
    }
}
