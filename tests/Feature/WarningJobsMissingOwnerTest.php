<?php

namespace Tests\Feature;

use App\Jobs\sendDeletionWarningEmails;
use App\Jobs\sendEmail;
use App\Jobs\sendExpiryWarningEmails;
use App\Models\Share;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class WarningJobsMissingOwnerTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function share(?User $owner, string $longId, Carbon $expiresAt, string $flag): Share
    {
        $share = Share::create([
            'user_id' => $owner?->id,
            'name' => $longId,
            'path' => "shares/{$longId}",
            'long_id' => $longId,
            'size' => 1,
            'file_count' => 1,
            'expires_at' => $expiresAt,
        ]);
        $share->forceFill([$flag => false])->save();

        return $share;
    }

    /**
     * A share whose owner was deleted (shares.user_id is SET NULL) must not stop the job:
     * it is marked processed without a mail, and the shares after it still get theirs.
     */
    private function assertOrphanDoesNotBlock(object $job, string $flag, Carbon $expiresAt): void
    {
        Bus::fake([sendEmail::class]);

        $gone = User::factory()->create();
        $orphan = $this->share($gone, 'orphan-share', $expiresAt, $flag);
        $gone->delete();
        $this->assertNull($orphan->fresh()->user_id, 'deleting the owner leaves the share with a NULL user_id');

        $owner = User::factory()->create();
        $healthy = $this->share($owner, 'healthy-share', $expiresAt, $flag);

        $job->handle();

        Bus::assertDispatchedTimes(sendEmail::class, 1);
        Bus::assertDispatched(sendEmail::class, fn (sendEmail $mail) => $mail->to === $owner->email);
        $this->assertTrue((bool) $orphan->fresh()->{$flag}, 'orphan share is marked processed');
        $this->assertTrue((bool) $healthy->fresh()->{$flag}, 'the next share still gets its warning');

        $job->handle();

        Bus::assertDispatchedTimes(sendEmail::class, 1);
    }

    public function test_expiry_warning_skips_a_share_whose_owner_was_deleted(): void
    {
        $this->assertOrphanDoesNotBlock(new sendExpiryWarningEmails(), 'sent_expiry_warning', now()->addHours(6));
    }

    public function test_deletion_warning_skips_a_share_whose_owner_was_deleted(): void
    {
        $this->assertOrphanDoesNotBlock(new sendDeletionWarningEmails(), 'sent_deletion_warning', now()->subDays(365));
    }
}
