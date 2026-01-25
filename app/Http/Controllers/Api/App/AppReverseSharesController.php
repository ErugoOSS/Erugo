<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ReverseShareInvite;
use App\Models\Setting;
use App\Mail\reverseShareInviteMail;
use App\Jobs\sendEmail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class AppReverseSharesController extends Controller
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
     * Create a reverse share invite (authenticated)
     * 
     * Request body:
     * - recipient_name: string (required) - Name of the recipient
     * - recipient_email: string (required) - Email of the recipient
     * - message: string (optional) - Message to include in the invite
     */
    public function invite(Request $request): JsonResponse
    {
        $allowReverseShares = Setting::where('key', 'allow_reverse_shares')->first()?->value;
        $allowReverseShares = filter_var($allowReverseShares, FILTER_VALIDATE_BOOLEAN);

        if (!$allowReverseShares) {
            return response()->json([
                'status' => 'error',
                'code' => 'REVERSE_SHARES_DISABLED',
                'message' => 'Reverse shares are not allowed'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:1000'],
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

        // Check if recipient is an existing non-guest user
        $existingUser = User::where('email', $request->recipient_email)
            ->where(function ($query) {
                $query->where('is_guest', false)
                    ->orWhereNull('is_guest');
            })
            ->first();

        $encryptedToken = null;
        $guestUserId = null;

        if ($existingUser) {
            // Existing user - no token, no guest user
            // They will need to log in with their credentials
            $guestUserId = null;
        } else {
            // Create a guest user for the invite
            $guestUser = User::create([
                'name' => $request->recipient_name,
                'email' => Str::random(20), // Random email for the guest user
                'password' => Hash::make(Str::random(20)), // Random password
                'is_guest' => true
            ]);
            $guestUserId = $guestUser->id;

            // Generate a token only for guest users
            $token = auth()->tokenById($guestUser->id);
            $encryptedToken = Crypt::encryptString($token);
        }

        $invite = ReverseShareInvite::create([
            'user_id' => $user->id,
            'guest_user_id' => $guestUserId,
            'recipient_name' => $request->recipient_name,
            'recipient_email' => $request->recipient_email,
            'message' => $request->message,
            'expires_at' => now()->addDays(7)
        ]);

        sendEmail::dispatch($request->recipient_email, reverseShareInviteMail::class, [
            'user' => $user,
            'invite' => $invite,
            'token' => $encryptedToken,
            'isExistingUser' => $existingUser !== null
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Invite sent successfully',
            'data' => [
                'invite' => $this->formatInvite($invite),
                'is_existing_user' => $existingUser !== null
            ]
        ]);
    }

    /**
     * Accept a reverse share invite with token (guest flow)
     * This is for guests who received an invite email with a token
     * 
     * Request body:
     * - token: string (required) - The encrypted token from the invite email
     * - device_name: string (optional) - Device name for the token
     */
    public function accept(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
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
            $token = Crypt::decryptString($request->token);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVALID_TOKEN',
                'message' => 'Invalid or expired token'
            ], 401);
        }

        $user = Auth::setToken($token)->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'code' => 'AUTH_UNAUTHORIZED',
                'message' => 'Invalid or expired token'
            ], 401);
        }

        $invite = ReverseShareInvite::where('guest_user_id', $user->id)->first();

        if (!$invite) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVITE_NOT_FOUND',
                'message' => 'Invite not found'
            ], 404);
        }

        if ($invite->isExpired()) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVITE_EXPIRED',
                'message' => 'Invite has expired'
            ], 410);
        }

        if ($invite->isUsed()) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVITE_ALREADY_USED',
                'message' => 'Invite has already been used'
            ], 410);
        }

        $invite->markAsUsed();

        // Invalidate the old token
        auth()->invalidate();

        // Return new tokens for the guest user
        return $this->respondWithTokens($user, $request->device_name, $invite);
    }

    /**
     * Accept a reverse share invite by ID (authenticated user flow)
     * This is for existing users who must log in first
     * 
     * Request body:
     * - invite_id: integer (required) - The ID of the invite to accept
     */
    public function acceptById(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invite_id' => ['required', 'integer'],
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

        $invite = ReverseShareInvite::find($request->invite_id);

        if (!$invite) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVITE_NOT_FOUND',
                'message' => 'Invite not found'
            ], 404);
        }

        // Verify the logged-in user's email matches the invite's recipient_email
        if (strtolower($user->email) !== strtolower($invite->recipient_email)) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVITE_EMAIL_MISMATCH',
                'message' => 'This invite was sent to a different email address'
            ], 403);
        }

        if ($invite->isExpired()) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVITE_EXPIRED',
                'message' => 'Invite has expired'
            ], 410);
        }

        if ($invite->isUsed()) {
            return response()->json([
                'status' => 'error',
                'code' => 'INVITE_ALREADY_USED',
                'message' => 'Invite has already been used'
            ], 410);
        }

        // Store the active invite ID on the user for the upload process to reference
        $invite->guest_user_id = $user->id;
        $invite->markAsUsed();

        return response()->json([
            'status' => 'success',
            'message' => 'Invite accepted. You can now upload files.',
            'data' => [
                'invite' => $this->formatInvite($invite)
            ]
        ]);
    }

    /**
     * Generate tokens and return response for App API
     */
    private function respondWithTokens(User $user, ?string $deviceName = null, ?ReverseShareInvite $invite = null): JsonResponse
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

        $data = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'access_token_expires_in' => $this->getAccessTokenTTL() * 60,
            'refresh_token_expires_in' => $this->getRefreshTokenTTL() * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'admin' => (bool) $user->admin,
                'is_guest' => (bool) $user->is_guest,
            ],
        ];

        if ($invite) {
            $data['invite'] = $this->formatInvite($invite);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Invite accepted successfully',
            'data' => $data
        ]);
    }

    /**
     * Format invite for response
     */
    private function formatInvite(ReverseShareInvite $invite): array
    {
        return [
            'id' => $invite->id,
            'recipient_name' => $invite->recipient_name,
            'recipient_email' => $invite->recipient_email,
            'message' => $invite->message,
            'expires_at' => $invite->expires_at,
            'used_at' => $invite->used_at,
            'completed_at' => $invite->completed_at,
            'created_at' => $invite->created_at,
        ];
    }
}
