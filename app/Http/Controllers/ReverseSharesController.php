<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\ReverseShareInvite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Jobs\sendEmail;
use App\Mail\reverseShareInviteMail;
use App\Models\Setting;


class ReverseSharesController extends Controller
{
    public function createInvite(Request $request)
    {

        $allowReverseShares = Setting::where('key', 'allow_reverse_shares')->first()->value;
        $allowReverseShares = filter_var($allowReverseShares, FILTER_VALIDATE_BOOLEAN);

        if (!$allowReverseShares) {
            return response()->json([
                'status' => 'error',
                'message' => 'Reverse shares are not allowed'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_email' => ['required', 'email', 'max:255']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
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

        $plainToken = null;
        $guestUserId = null;

        if ($existingUser) {
            // Existing user — no guest account, no token.
            // The invite link will contain invite_id instead; the recipient
            // must log in with their own credentials to accept it.
        } else {
            // New (guest) user — create a throw-away account with a random
            // password that nobody knows, so the only way in is via the link.
            $guestUser = User::create([
                'name' => $request->recipient_name,
                'email' => Str::random(20), // not a real address; never used for auth
                'password' => Hash::make(Str::random(32)),
                'is_guest' => true
            ]);
            $guestUserId = $guestUser->id;

            // Generate a cryptographically secure random token.
            // Only the SHA-256 hash is stored in the database; the plain-text
            // token is placed in the invite link and never persisted, so even a
            // full DB dump does not let an attacker accept the invite.
            // The token's validity is governed by invite.expires_at (7 days),
            // not by a JWT TTL, which fixes the "link expires after 60 minutes"
            // bug present in the previous JWT-based approach.
            $plainToken = Str::random(64);
        }

        $invite = ReverseShareInvite::create([
            'user_id' => $user->id,
            'guest_user_id' => $guestUserId,
            'guest_token' => $plainToken ? hash('sha256', $plainToken) : null,
            'recipient_name' => $request->recipient_name,
            'recipient_email' => $request->recipient_email,
            'message' => $request->message,
            'expires_at' => now()->addDays(7)
        ]);

        sendEmail::dispatch($request->recipient_email, reverseShareInviteMail::class, [
            'user' => $user,
            'invite' => $invite,
            'token' => $plainToken, // null for existing users
            'isExistingUser' => $existingUser !== null
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'invite' => $invite
            ]
        ]);
    }
}
