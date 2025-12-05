<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

use App\Models\User;
use App\Models\ReverseShareInvite;
use App\Models\Setting;

use App\Jobs\sendEmail;
use App\Mail\reverseShareInviteMail;

class ReverseSharesController extends Controller
{
    /**
     * Create a reverse share invite and send an email to the recipient.
     */
    public function createInvite(Request $request)
    {
        // Check if reverse shares are allowed
        $allowReverseSharesSetting = Setting::where('key', 'allow_reverse_shares')->first();

        $allowReverseShares = $allowReverseSharesSetting
            ? filter_var($allowReverseSharesSetting->value, FILTER_VALIDATE_BOOLEAN)
            : false;

        if (!$allowReverseShares) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Reverse shares are not allowed',
            ], 400);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'recipient_name'  => ['required', 'string', 'max:255'],
            'recipient_email' => ['required', 'email', 'max:255'],
            'message'         => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'data'    => [
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        // Authenticated owner of the reverse share
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        // Find or create the guest user
        $guestUser = User::where('email', $request->recipient_email)->first();

        if (!$guestUser) {
            // Create a guest user that cannot log in normally
            $guestUser = User::create([
                'name'     => $request->recipient_name,
                // We don't actually need their real email for the internal guest account
                'email'    => Str::random(20),
                'password' => Hash::make(Str::random(20)),
                'is_guest' => true,
            ]);
        }

        // IMPORTANT: always generate a token for the guest user
        $token = auth()->tokenById($guestUser->id);
        $encryptedToken = Crypt::encryptString($token);

        // Create the reverse share invite
        $invite = ReverseShareInvite::create([
            'user_id'         => $user->id,
            'guest_user_id'   => $guestUser->id,
            'recipient_name'  => $request->recipient_name,
            'recipient_email' => $request->recipient_email,
            'message'         => $request->message,
            'expires_at'      => now()->addDays(7),
        ]);

        // Send the invite email
        sendEmail::dispatch(
            $request->recipient_email,
            reverseShareInviteMail::class,
            [
                'user'   => $user,
                'invite' => $invite,
                'token'  => $encryptedToken,
            ]
        );

        return response()->json([
            'status' => 'success',
            'data'   => [
                'invite' => $invite,
            ],
        ]);
    }
}

