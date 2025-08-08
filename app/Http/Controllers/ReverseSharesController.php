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

        $guestUser = User::where('email', $request->recipient_email)->first();

        if (!$guestUser) {
            $guestUser = User::create([
                'name' => $request->recipient_name,
                'email' => Str::random(20), //we don't need a real email for the guest user
                'password' => Hash::make(Str::random(20)), //set a random password so the user can't login
                'is_guest' => true
            ]);
        }

        $token = $token = auth()->tokenById($guestUser->id);
        $encryptedToken = Crypt::encryptString($token);

        $invite = ReverseShareInvite::create([
            'user_id' => $user->id,
            'guest_user_id' => $guestUser->id,
            'recipient_name' => $request->recipient_name,
            'recipient_email' => $request->recipient_email,
            'message' => $request->message,
            'expires_at' => now()->addDays(7)
        ]);

        sendEmail::dispatch($request->recipient_email, reverseShareInviteMail::class, [
            'user' => $user,
            'invite' => $invite,
            'token' => $encryptedToken
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'invite' => $invite
            ]
        ]);
    }
}
