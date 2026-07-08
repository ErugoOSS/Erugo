<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\ReverseShareInvite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
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

        $sendEmail = $request->boolean('send_email', true);

        $validator = Validator::make($request->all(), [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_email' => [$sendEmail ? 'required' : 'nullable', 'email', 'max:255'],
            'send_email' => ['nullable', 'boolean']
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

        $finalEmail = $request->recipient_email ?? (Str::random(20) . '@guest.local'); //we don't need a real email for the guest user
        $existingUser = null;

        if ($request->recipient_email) {
            // Check if recipient is an existing non-guest user
            $existingUser = User::where('email', $request->recipient_email)
                ->where(function ($query) {
                    $query->where('is_guest', false)
                        ->orWhereNull('is_guest');
                })
                ->first();
        }

        $encryptedToken = null;
        $guestUserId = null;
        $inviteUrl = null;

        if ($existingUser) {
            // Existing user - no token, no guest user
            // They will need to log in with their credentials
            $guestUserId = null;
        } else {
            // Create a guest user for the invite
            $guestUser = User::create([
                'name' => $request->recipient_name,
                'email' => $finalEmail,
                'password' => Hash::make(Str::random(20)), //set a random password so the user can't login
                'is_guest' => true
            ]);
            $guestUserId = $guestUser->id;

            // Generate a token only for guest users. Set validity to 24h
            $token = auth()->setTTL(1440)->tokenById($guestUser->id);
            $encryptedToken = Crypt::encryptString($token);
        }

        $invite = ReverseShareInvite::create([
            'user_id' => $user->id,
            'guest_user_id' => $guestUserId,
            'recipient_name' => $request->recipient_name,
            'recipient_email' => $finalEmail,
            'message' => $request->message,
            'expires_at' => now()->addDays(7)
        ]);

        if ($sendEmail && $request->recipient_email) {
            sendEmail::dispatch($request->recipient_email, reverseShareInviteMail::class, [
                'user' => $user,
                'invite' => $invite,
                'token' => $encryptedToken, // Will be null for existing users
                'isExistingUser' => $existingUser !== null
            ]);
        } else {
            $appUrlSetting = Setting::where('key', 'application_url')->first();
            $baseUrl = rtrim($appUrlSetting ? $appUrlSetting->value : url(''), '/');
    
            // On applique la logique de ton template
            if ($existingUser) {
                $inviteUrl = $baseUrl . '/?invite_id=' . $invite->id;
            } else {
                $inviteUrl = $baseUrl . '/?invite_token=' . urlencode($encryptedToken);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'invite' => $invite,
                'link' => $inviteUrl
            ]
        ]);
    }
}
