<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TusdHookKeyTest extends TestCase
{
    use RefreshDatabase;

    private const HOOK_KEY = 'test-hook-key';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config(['services.tusd.hook_secret' => self::HOOK_KEY]);
    }

    private function hook(array $headers = [])
    {
        // post-receive has no side effects, so it shows whether the request
        // got past the checks without touching uploads.
        return $this->postJson('/api/tusd-hooks', ['Type' => 'post-receive'], $headers);
    }

    public function test_tusd_hook_without_key_is_refused(): void
    {
        $this->hook()->assertStatus(403);
    }

    public function test_tusd_hook_with_wrong_key_is_refused(): void
    {
        $this->hook(['Authorization' => 'Basic ' . base64_encode('tusd:wrong')])->assertStatus(403);
        $this->hook(['Hook-Secret' => 'wrong'])->assertStatus(403);
    }

    public function test_tusd_hook_accepts_key_as_basic_auth_password(): void
    {
        $this->hook(['Authorization' => 'Basic ' . base64_encode('tusd:' . self::HOOK_KEY)])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_tusd_hook_accepts_key_in_hook_secret_header(): void
    {
        $this->hook(['Hook-Secret' => self::HOOK_KEY])->assertOk();
    }

    public function test_tusd_hook_is_refused_when_no_key_is_configured(): void
    {
        config(['services.tusd.hook_secret' => null]);

        $this->hook(['Authorization' => 'Basic ' . base64_encode('tusd:')])->assertStatus(403);
        $this->hook(['Hook-Secret' => ''])->assertStatus(403);
    }
}
