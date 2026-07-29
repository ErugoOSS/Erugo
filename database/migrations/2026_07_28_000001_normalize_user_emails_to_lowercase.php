<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Normalize all user email addresses to lowercase.
     *
     * If two accounts share the same email under different casing (a collision),
     * the oldest account (lowest ID) is kept and normalized. Duplicate accounts
     * are quarantined: their email is suffixed with __dup_{id} and their account
     * is deactivated. An admin can then review and merge or remove them.
     *
     * Password reset tokens are also normalized so any tokens issued before this
     * migration continues to work.
     */
    public function up(): void
    {
        // Detect collisions: groups where LOWER(email) maps to more than one account
        $collisions = DB::table('users')
            ->select(DB::raw('LOWER(email) as normalized_email'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('LOWER(email)'))
            ->having('cnt', '>', 1)
            ->pluck('normalized_email');

        foreach ($collisions as $normalizedEmail) {
            $duplicates = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->orderBy('id')
                ->get();

            $isFirst = true;
            foreach ($duplicates as $duplicate) {
                if ($isFirst) {
                    $isFirst = false;
                    continue; // Keep the oldest account; normalize it below with the rest
                }

                // Quarantine: suffix the email and deactivate
                $quarantineEmail = $duplicate->email . '__dup_' . $duplicate->id;
                DB::table('users')
                    ->where('id', $duplicate->id)
                    ->update([
                        'email'      => $quarantineEmail,
                        'active'     => false,
                        'updated_at' => now(),
                    ]);

                Log::warning(
                    'Email normalization collision: user ' . $duplicate->id .
                    ' quarantined. Original email: ' . $duplicate->email .
                    ' — review and merge or remove this account.'
                );
            }
        }

        // Normalize all remaining (non-quarantined) emails to lowercase
        DB::statement('UPDATE users SET email = LOWER(email), updated_at = ? WHERE email != LOWER(email)', [now()]);

        // Normalize any pending password reset tokens so they still match
        if (DB::getSchemaBuilder()->hasTable('password_reset_tokens')) {
            DB::statement('UPDATE password_reset_tokens SET email = LOWER(email) WHERE email != LOWER(email)');
        }
    }

    public function down(): void
    {
        // Original casing is not preserved — this migration cannot be reversed.
        // If rollback is required, restore from a database backup taken before migration.
    }
};
