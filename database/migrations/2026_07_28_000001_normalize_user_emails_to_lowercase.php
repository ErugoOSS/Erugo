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
     * migration continue to work.
     *
     * Logging:
     *   - Collisions found    → dedicated report written to storage/logs/email-normalization-{date}.log
     *   - Normalization only  → single info line in the standard Laravel log
     *   - Nothing to do       → no log entry
     */
    public function up(): void
    {
        // ── Pre-flight counts ────────────────────────────────────────────────
        $usersToNormalize = DB::table('users')
            ->whereRaw('email != LOWER(email)')
            ->count();

        $tokensToNormalize = DB::getSchemaBuilder()->hasTable('password_reset_tokens')
            ? DB::table('password_reset_tokens')->whereRaw('email != LOWER(email)')->count()
            : 0;

        // ── Detect collision groups ──────────────────────────────────────────
        $collisionGroups = DB::table('users')
            ->select(DB::raw('LOWER(email) as normalized_email'), DB::raw('COUNT(*) as cnt'))
            ->groupBy(DB::raw('LOWER(email)'))
            ->having('cnt', '>', 1)
            ->pluck('normalized_email');

        // ── Nothing to do ────────────────────────────────────────────────────
        if ($usersToNormalize === 0 && $tokensToNormalize === 0 && $collisionGroups->isEmpty()) {
            return;
        }

        // Everything below runs in one transaction. Without it, a failure
        // part-way through left users renamed but the migration unrecorded,
        // and every later start failed on the same step.
        $quarantined = [];
        $tokensDropped = 0;

        DB::transaction(function () use ($collisionGroups, $tokensToNormalize, &$quarantined, &$tokensDropped) {
            $hasTokens = DB::getSchemaBuilder()->hasTable('password_reset_tokens');

        // ── Quarantine collisions ────────────────────────────────────────────
        foreach ($collisionGroups as $normalizedEmail) {
            $group = DB::table('users')
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->orderBy('id')
                ->get();

            $keptId = $group->first()->id;

            foreach ($group->skip(1) as $duplicate) {
                $quarantineEmail = $duplicate->email . '__dup_' . $duplicate->id;

                DB::table('users')
                    ->where('id', $duplicate->id)
                    ->update([
                        'email'      => $quarantineEmail,
                        'active'     => false,
                        'updated_at' => now(),
                    ]);

                // A reset token issued to the deactivated account would, once
                // lowercased, reset the kept account instead. Drop it.
                if ($hasTokens) {
                    $tokensDropped += DB::table('password_reset_tokens')
                        ->where('email', $duplicate->email)
                        ->delete();
                }

                $quarantined[] = [
                    'id'             => $duplicate->id,
                    'name'           => $duplicate->name,
                    'original_email' => $duplicate->email,
                    'kept_id'        => $keptId,
                    'kept_email'     => strtolower($normalizedEmail),
                ];
            }
        }

        // ── Normalize remaining emails ────────────────────────────────────────
        DB::statement(
            'UPDATE users SET email = LOWER(email), updated_at = ? WHERE email != LOWER(email)',
            [now()]
        );

        if ($hasTokens && $tokensToNormalize > 0) {
            // password_reset_tokens.email is the primary key, so two tokens
            // that differ only by case can't both be lowercased. Keep the
            // newest of each group.
            $tokenGroups = DB::table('password_reset_tokens')
                ->select(DB::raw('LOWER(email) as normalized_email'), DB::raw('COUNT(*) as cnt'))
                ->groupBy(DB::raw('LOWER(email)'))
                ->having('cnt', '>', 1)
                ->pluck('normalized_email');

            foreach ($tokenGroups as $normalizedEmail) {
                $rows = DB::table('password_reset_tokens')
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->orderByDesc('created_at')
                    ->get();
                foreach ($rows->skip(1) as $row) {
                    $tokensDropped += DB::table('password_reset_tokens')->where('email', $row->email)->delete();
                }
            }

            DB::statement(
                'UPDATE password_reset_tokens SET email = LOWER(email) WHERE email != LOWER(email)'
            );
        }
        });

        if ($tokensDropped > 0) {
            Log::warning('Email normalization: removed ' . $tokensDropped . ' password reset token(s) that clashed by case or belonged to a deactivated account.');
        }

        // ── Logging ──────────────────────────────────────────────────────────
        if (!empty($quarantined)) {
            $this->writeCollisionReport($usersToNormalize, $tokensToNormalize, $quarantined);
        } else {
            $parts = [];
            if ($usersToNormalize > 0) {
                $parts[] = $usersToNormalize . ' user email' . ($usersToNormalize === 1 ? '' : 's') . ' normalized';
            }
            if ($tokensToNormalize > 0) {
                $parts[] = $tokensToNormalize . ' password reset token' . ($tokensToNormalize === 1 ? '' : 's') . ' normalized';
            }
            Log::info('Email normalization migration: ' . implode(', ', $parts) . '. No collisions found.');
        }
    }

    public function down(): void
    {
        // Original casing is not preserved — this migration cannot be reversed.
        // If rollback is required, restore from a database backup taken before migration.
    }

    private function writeCollisionReport(int $usersNormalized, int $tokensNormalized, array $quarantined): void
    {
        $timestamp  = now()->format('Y-m-d H:i:s') . ' UTC';
        $dateStamp  = now()->format('Y-m-d');
        $logPath    = storage_path('logs/email-normalization-' . $dateStamp . '.log');

        $collisionGroups = count(array_unique(array_column($quarantined, 'kept_email')));

        $lines = [];
        $lines[] = 'Erugo — Email Normalization Report';
        $lines[] = 'Generated: ' . $timestamp;
        $lines[] = '';
        $lines[] = 'Summary';
        $lines[] = '-------';
        $lines[] = 'Users normalized    : ' . $usersNormalized;
        if ($tokensNormalized > 0) {
            $lines[] = 'Reset tokens normalized: ' . $tokensNormalized;
        }
        $lines[] = 'Collision groups    : ' . $collisionGroups;
        $lines[] = 'Accounts quarantined: ' . count($quarantined);
        $lines[] = '';
        $lines[] = 'Quarantined Accounts (require admin review)';
        $lines[] = '-------------------------------------------';
        $lines[] = 'These accounts were deactivated because another account with the same';
        $lines[] = 'email address (different casing) already existed. The older account was';
        $lines[] = 'kept. Review each entry below and either merge the data into the kept';
        $lines[] = 'account or delete the quarantined record.';
        $lines[] = '';

        foreach ($quarantined as $q) {
            $lines[] = 'Quarantined ID : ' . $q['id'] . ' (' . $q['name'] . ')';
            $lines[] = '  Original email : ' . $q['original_email'];
            $lines[] = '  Kept account   : ID ' . $q['kept_id'] . ' <' . $q['kept_email'] . '>';
            $lines[] = '  Status         : deactivated; email suffixed with __dup_' . $q['id'];
            $lines[] = '';
        }

        // The database changes are already made at this point, so a report
        // that can't be written must not fail the migration.
        $reportWritten = @file_put_contents($logPath, implode("\n", $lines) . "\n", FILE_APPEND) !== false;

        // Also send a warning to the standard log so it appears in monitoring,
        // with one line per account so nothing depends on the report file.
        Log::warning(
            'Email normalization: ' . count($quarantined) . ' account(s) quarantined due to case collisions. ' .
            ($reportWritten ? 'Review: ' . $logPath : 'The report file could not be written.')
        );
        foreach ($quarantined as $q) {
            Log::warning('Email normalization: deactivated user ' . $q['id'] . ' (' . $q['original_email'] . '), ' .
                'which clashed with user ' . $q['kept_id'] . ' <' . $q['kept_email'] . '>. ' .
                'Its email now ends in __dup_' . $q['id'] . '.');
        }

        // Migrations run from the container's start script, so print a short
        // notice there too where an admin watching docker logs will see it.
        if (app()->runningInConsole() && !app()->runningUnitTests()) {
            fwrite(STDOUT, 'Email normalization: ' . count($quarantined) . ' account(s) with clashing email case were deactivated. ' .
                'See ' . ($reportWritten ? $logPath : 'storage/logs/laravel.log') . PHP_EOL);
        }
    }
};
