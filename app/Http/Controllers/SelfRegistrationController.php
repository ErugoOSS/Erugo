<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Models\User;
use App\Models\Setting;
use App\Mail\emailVerificationMail;
use App\Jobs\sendEmail;
use Carbon\Carbon;

class SelfRegistrationController extends Controller
{
    /**
     * Register a new user via self-registration
     */
    public function register(Request $request)
    {
        // Check if self-registration is enabled
        $enabled = Setting::where('key', 'self_registration_enabled')->first();
        if (!$enabled || $enabled->value !== 'true') {
            return response()->json([
                'status' => 'error',
                'message' => 'Self-registration is not enabled'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
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

        // Check domain restrictions
        $allowAnyDomain = Setting::where('key', 'self_registration_allow_any_domain')->first();
        $allowedDomains = Setting::where('key', 'self_registration_allowed_domains')->first();

        if (!$allowAnyDomain || $allowAnyDomain->value !== 'true') {
            // Domain restriction is enabled
            $email = $request->email;
            $domain = strtolower(substr(strrchr($email, "@"), 1));
            
            $domains = [];
            if ($allowedDomains && !empty($allowedDomains->value)) {
                $domains = array_map('trim', array_map('strtolower', explode(',', $allowedDomains->value)));
            }

            if (empty($domains) || !in_array($domain, $domains)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Registration is not allowed for this email domain'
                ], 403);
            }
        }

        // Generate 6-digit verification code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(15);

        try {
            $user = User::create([
                'email' => $request->email,
                'name' => $request->name,
                'password' => Hash::make($request->password),
                'admin' => false,
                'active' => false, // Account is inactive until verified
                'must_change_password' => false,
                'email_verification_code' => $code,
                'email_verification_code_expires_at' => $expiresAt,
            ]);

            // Send verification email
            sendEmail::dispatch($user->email, emailVerificationMail::class, ['code' => $code, 'user' => $user]);

            return response()->json([
                'status' => 'success',
                'message' => 'Registration successful. Please check your email for the verification code.',
                'data' => [
                    'email' => $user->email
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create account'
            ], 500);
        }
    }

    /**
     * Verify email with the 6-digit code
     */
    public function verifyEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
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
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or verification code'
            ], 400);
        }

        // Check if already verified
        if ($user->active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is already verified'
            ], 400);
        }

        // Check if code matches
        if ($user->email_verification_code !== $request->code) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or verification code'
            ], 400);
        }

        // Check if code has expired
        if (Carbon::now()->isAfter($user->email_verification_code_expires_at)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verification code has expired. Please request a new one.'
            ], 400);
        }

        // Activate the account
        $user->active = true;
        $user->email_verification_code = null;
        $user->email_verification_code_expires_at = null;
        $user->email_verified_at = Carbon::now();
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Email verified successfully. You can now log in.'
        ]);
    }

    /**
     * Resend verification code
     */
    public function resendCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
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

        // Always return success to prevent email enumeration
        if (!$user || $user->active) {
            return response()->json([
                'status' => 'success',
                'message' => 'If an unverified account exists with this email, a new verification code has been sent.'
            ]);
        }

        // Generate new code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes(15);

        $user->email_verification_code = $code;
        $user->email_verification_code_expires_at = $expiresAt;
        $user->save();

        // Send verification email
        sendEmail::dispatch($user->email, emailVerificationMail::class, ['code' => $code, 'user' => $user]);

        return response()->json([
            'status' => 'success',
            'message' => 'If an unverified account exists with this email, a new verification code has been sent.'
        ]);
    }

    /**
     * Get self-registration settings (public endpoint for login page)
     */
    public function getSettings()
    {
        $enabled = Setting::where('key', 'self_registration_enabled')->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'self_registration_enabled' => $enabled ? $enabled->value === 'true' : false
            ]
        ]);
    }
}


