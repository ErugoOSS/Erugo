<?php

namespace Tests\Feature;

use App\Jobs\sendEmail;
use App\Jobs\sendExpiredWarningEmails;
use App\Models\Share;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SendExpiredWarningEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function expiredShare(?User $owner, string $longId): Share
    {
        $share = Share::create([
            'user_id' => $owner?->id,
            'name' => $longId,
            'path' => "shares/{$longId}",
            'long_id' => $longId,
            'size' => 1,
            'file_count' => 1,
            'expires_at' => now()->subDay(),
        ]);
        $share->forceFill(['sent_expired' => false])->save();

        return $share;
    }

    public function test_share_whose_owner_was_deleted_does_not_block_the_others(): void
    {
        Bus::fake([sendEmail::class]);

        $gone = User::factory()->create();
        $orphan = $this->expiredShare($gone, 'orphan-share');
        $gone->delete();
        $this->assertNull($orphan->fresh()->user_id, 'deleting the owner leaves the share with a NULL user_id');

        $owner = User::factory()->create();
        $healthy = $this->expiredShare($owner, 'healthy-share');

        (new sendExpiredWarningEmails())->handle();

        Bus::assertDispatchedTimes(sendEmail::class, 1);
        Bus::assertDispatched(sendEmail::class, fn (sendEmail $job) => $job->to === $owner->email);
        $this->assertTrue((bool) $orphan->fresh()->sent_expired, 'orphan share is marked processed');
        $this->assertTrue((bool) $healthy->fresh()->sent_expired, 'the next share still gets its warning');
    }

    public function test_processed_orphan_share_is_not_picked_up_again(): void
    {
        Bus::fake([sendEmail::class]);

        $gone = User::factory()->create();
        $this->expiredShare($gone, 'orphan-share');
        $gone->delete();

        (new sendExpiredWarningEmails())->handle();
        (new sendExpiredWarningEmails())->handle();

        Bus::assertNotDispatched(sendEmail::class);
        $this->assertSame(0, Share::where('sent_expired', false)->count());
    }
}
