<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\CreateUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Jobs\sendEmail;
use App\Mail\accountCreatedMail;
use App\Models\Download;
use App\Models\File;
use App\Models\ReverseShareInvite;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordFacade;
use Illuminate\Support\Str;

class AppUsersAdminController extends Controller
{
    /**
     * List all non-guest users with pagination.
     */
    public function index(): JsonResponse
    {
        $perPage = min((int) request()->input('per_page', 20), 100);
        
        $users = User::where('is_guest', false)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => [
                'users' => $users->getCollection()->map(fn($user) => $this->formatUser($user)),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'total_pages' => $users->lastPage(),
                    'total_items' => $users->total(),
                    'per_page' => $users->perPage(),
                ],
            ]
        ]);
    }

    /**
     * Get a single user by ID.
     */
    public function show($id): JsonResponse
    {
        $user = User::where('is_guest', false)->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'USER_NOT_FOUND',
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $this->formatUser($user)
            ]
        ]);
    }

    /**
     * Create a new user.
     * Auto-generates password and sends account creation email.
     */
    public function create(CreateUserRequest $request): JsonResponse
    {
        try {
            $user = User::create([
                'email' => $request->email,
                'name' => $request->name,
                'admin' => $request->boolean('admin', false),
                'password' => Hash::make(Str::random(20)),
                'active' => true,
                'must_change_password' => false,
            ]);

            // Migrate any existing reverse share invites to the new user
            try {
                $existingInvites = ReverseShareInvite::where('recipient_email', $user->email)->get();
                foreach ($existingInvites as $invite) {
                    // Delete the old guest user if it exists
                    if ($invite->guestUser && $invite->guestUser->is_guest) {
                        $invite->guestUser->delete();
                    }
                    // Point the invite to the new real user
                    $invite->guest_user_id = $user->id;
                    $invite->save();
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to migrate reverse share invites for user ' . $user->email . ': ' . $e->getMessage());
            }

            // Send account creation email with password reset token
            $token = PasswordFacade::createToken($user);
            sendEmail::dispatch($user->email, accountCreatedMail::class, ['token' => $token, 'user' => $user]);

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => [
                    'user' => $this->formatUser($user)
                ]
            ], 201);
        } catch (\Exception $e) {
            \Log::error('Error creating user: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 'USER_CREATE_FAILED',
                'message' => 'Failed to create user'
            ], 500);
        }
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, $id): JsonResponse
    {
        $user = User::where('is_guest', false)->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'USER_NOT_FOUND',
                'message' => 'User not found'
            ], 404);
        }

        try {
            $user->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => [
                    'user' => $this->formatUser($user->fresh())
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating user ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 'USER_UPDATE_FAILED',
                'message' => 'Failed to update user'
            ], 500);
        }
    }

    /**
     * Delete a user.
     * Cannot delete yourself.
     */
    public function delete($id): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::where('is_guest', false)->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'USER_NOT_FOUND',
                'message' => 'User not found'
            ], 404);
        }

        // Prevent self-deletion
        if ($currentUser->id == $user->id) {
            return response()->json([
                'status' => 'error',
                'code' => 'CANNOT_DELETE_SELF',
                'message' => 'Cannot delete your own account'
            ], 400);
        }

        try {
            // Collect guest users to delete (from invites created by this user)
            $guestUsersToDelete = [];
            $invitesCreatedByUser = ReverseShareInvite::where('user_id', $user->id)->get();
            foreach ($invitesCreatedByUser as $invite) {
                if ($invite->guest_user_id) {
                    $guestUser = User::find($invite->guest_user_id);
                    if ($guestUser && $guestUser->is_guest) {
                        $guestUsersToDelete[] = $guestUser;
                    }
                }
            }

            // Delete all invites created by this user first (removes FK references to guest users)
            ReverseShareInvite::where('user_id', $user->id)->delete();

            // Set guest_user_id to null where this user is the guest
            ReverseShareInvite::where('guest_user_id', $user->id)->update(['guest_user_id' => null]);

            // Now safely delete the guest users
            foreach ($guestUsersToDelete as $guestUser) {
                ReverseShareInvite::where('guest_user_id', $guestUser->id)->update(['guest_user_id' => null]);
                $this->cleanupUserData($guestUser);
                $guestUser->delete();
            }

            // Clean up all the user's data (shares, files, downloads)
            $this->cleanupUserData($user);

            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error deleting user ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 'USER_DELETE_FAILED',
                'message' => 'Failed to delete user'
            ], 500);
        }
    }

    /**
     * Force password reset for a user.
     * Cannot reset your own password.
     */
    public function forceResetPassword($id): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::where('is_guest', false)->find($id);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'USER_NOT_FOUND',
                'message' => 'User not found'
            ], 404);
        }

        // Prevent self password reset through this endpoint
        if ($currentUser->id == $user->id) {
            return response()->json([
                'status' => 'error',
                'code' => 'CANNOT_RESET_SELF',
                'message' => 'Cannot force reset your own password'
            ], 400);
        }

        try {
            // Set a random password to invalidate the current one
            $user->password = Hash::make(Str::random(64));
            $user->must_change_password = true;
            $user->remember_token = null;
            $user->save();

            // Send password reset email
            $token = PasswordFacade::createToken($user);
            sendEmail::dispatch($user->email, \App\Mail\passwordResetMail::class, ['token' => $token, 'user' => $user]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password reset forced successfully. User will receive an email to set a new password.',
                'data' => [
                    'user' => $this->formatUser($user->fresh())
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error forcing password reset for user ' . $id . ': ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'code' => 'PASSWORD_RESET_FAILED',
                'message' => 'Failed to force password reset'
            ], 500);
        }
    }

    /**
     * Format user for API response.
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'admin' => (bool) $user->admin,
            'active' => (bool) $user->active,
            'must_change_password' => (bool) $user->must_change_password,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Clean up all data associated with a user (shares, files, downloads).
     */
    private function cleanupUserData(User $user): void
    {
        $shares = $user->shares;

        foreach ($shares as $share) {
            Download::where('share_id', $share->id)->delete();
            File::where('share_id', $share->id)->delete();
            $share->cleanFiles(true);
            $share->delete();
        }
    }
}
