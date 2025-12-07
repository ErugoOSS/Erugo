<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use App\Mail\passwordResetMail;
use App\Jobs\sendEmail;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\ReverseShareInvite;
use Illuminate\Support\Facades\Crypt;


class AuthController extends Controller
{

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
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

        $credentials = $request->only('email', 'password');

        Auth::attempt($credentials);
        $user = Auth::user();


        return $this->respondWithToken($user);
    }

    //refresh the token
    public function refresh()
    {
        //grab the token from refresh_token cookie
        $refreshToken = request()->cookie('refresh_token');
        if (!$refreshToken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        //get the user from the token
        $user = Auth::setToken($refreshToken)->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        return $this->respondWithToken($user);
    }

    //logout the user
    public function logout()
    {
        //invalidate the token
        Auth::logout();

        //clear the refresh_token cookie
        $cookie = cookie('refresh_token', '', 0, null, null, false, true);
        return response()->json([
            'status' => 'success',
            'message' => 'Logout successful'
        ])->withCookie($cookie);
    }

    public function acceptReverseShareInvite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
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

        $token = Crypt::decryptString($request->token);

        $user = Auth::setToken($token)->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $invite = ReverseShareInvite::where('guest_user_id', $user->id)->first();

        if (!$invite) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invite not found'
            ], 404);
        }

        if ($invite->isExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invite expired'
            ], 404);
        }

        if ($invite->isUsed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invite already used'
            ], 404);
        }

        $invite->markAsUsed();

        //invalidate the token
        auth()->invalidate();

        return $this->respondWithToken($user);
    }

    private function respondWithToken($user)
    {
        $token = Auth::login($user);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        $twentyFourHours = 60 * 60 * 24;
        $refreshToken = Auth::setTTL($twentyFourHours)->tokenById($user->id);

        $cookie = cookie('refresh_token', $refreshToken, $twentyFourHours, null, null, false, true);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => Auth::factory()->getTTL() * 60,
                'guest' => $user->is_guest
            ]
        ])->withCookie($cookie);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
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

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // just respond with success so we don't leak information
            return response()->json([
                'status' => 'success',
                'message' => 'Password reset email sent'
            ]);
        }

        $token = Password::createToken($user);

        sendEmail::dispatch($user->email, passwordResetMail::class, ['token' => $token, 'user' => $user]);

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset email sent'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
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

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        $twentyFourHours = 60 * 60 * 24;
        if ($status === Password::PASSWORD_RESET) {
            $user = User::where('email', $request->email)->first();
            $refreshToken = Auth::setTTL($twentyFourHours)->tokenById($user->id);
            $cookie = cookie('refresh_token', $refreshToken, $twentyFourHours, null, null, false, true);
            return response()->json([
                'status' => 'success',
                'message' => 'Password reset successfully'
            ])->withCookie($cookie);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Password reset failed'
        ], 400);
    }

    /**
     * Accept a reverse share invite by ID (for existing/authenticated users)
     * This requires the user to be logged in and their email must match the invite's recipient_email
     */
    public function acceptReverseShareInviteById(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invite_id' => 'required|integer',
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

        $invite = ReverseShareInvite::find($request->invite_id);

        if (!$invite) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invite not found'
            ], 404);
        }

        // Verify the logged-in user's email matches the invite's recipient_email
        if (strtolower($user->email) !== strtolower($invite->recipient_email)) {
            return response()->json([
                'status' => 'error',
                'message' => 'This invite was sent to a different email address'
            ], 403);
        }

        if ($invite->isExpired()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invite expired'
            ], 400);
        }

        if ($invite->isUsed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invite already used'
            ], 400);
        }

        // Store the active invite ID on the user for the upload process to reference
        // We'll use the session or a simple approach - store it in the invite itself
        // by setting the guest_user_id to the current user's ID
        $invite->guest_user_id = $user->id;
        $invite->markAsUsed(); // Mark as used so UploadsController can find it

        return response()->json([
            'status' => 'success',
            'message' => 'Invite accepted. You can now upload files.',
            'data' => [
                'invite' => $invite
            ]
        ]);
    }
}
