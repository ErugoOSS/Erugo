<?php

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * Password reset tokens come from two brokers (see config/auth.php):
 *
 * - "users" is the normal forgot-password flow. Tokens last 60 minutes.
 * - "account_setup" is for the welcome email an admin's new user gets, which
 *   people often don't open straight away. Tokens last 24 hours.
 *
 * Each broker keeps its tokens in its own table, so a forgot-password token
 * can never be redeemed under the longer expiry. Both links go to the same
 * reset page, so reset() tries the normal broker first and then the setup one.
 */
class PasswordTokens
{
    public const SETUP_BROKER = 'account_setup';

    /**
     * Token for the account-created email.
     */
    public static function createSetupToken(User $user): string
    {
        return Password::broker(self::SETUP_BROKER)->createToken($user);
    }

    /**
     * Reset a password with a token from either broker. Returns a
     * Password::* status string, like Password::reset().
     */
    public static function reset(array $credentials, Closure $callback): string
    {
        $status = Password::broker()->reset($credentials, $callback);

        if ($status === Password::INVALID_TOKEN) {
            $status = Password::broker(self::SETUP_BROKER)->reset($credentials, $callback);
        }

        if ($status === Password::PASSWORD_RESET && isset($credentials['email'])) {
            // The broker only removes its own token. Drop the other one too, so
            // an old welcome link stops working once the password is set.
            self::forget($credentials['email']);
        }

        return $status;
    }

    /**
     * Remove every outstanding token for an email, from both brokers.
     */
    public static function forget(string $email): void
    {
        foreach (['users', self::SETUP_BROKER] as $broker) {
            $table = config("auth.passwords.{$broker}.table");
            if ($table) {
                DB::table($table)->where('email', $email)->delete();
            }
        }
    }
}
