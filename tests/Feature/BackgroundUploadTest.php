<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class BackgroundUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('backgrounds');

        $this->admin = User::createAccount([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ], ['active' => true, 'admin' => true]);
    }

    private function upload(UploadedFile $file)
    {
        return $this->withHeader('Authorization', 'Bearer ' . JWTAuth::fromUser($this->admin))
            ->post('/api/settings/backgrounds', ['background_image' => $file], ['Accept' => 'application/json']);
    }

    // UploadedFile::fake() reports a MIME type based on the file name, which
    // would hide what these tests are checking. A real temp file makes
    // Laravel and finfo read the contents.
    private function realFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bg');
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    public function test_real_image_is_accepted(): void
    {
        $this->upload($this->realFile('sky.png', $this->pngBytes()))
            ->assertOk()
            ->assertJsonPath('data.file', 'sky.png');

        Storage::disk('backgrounds')->assertExists('sky.png');
    }

    public function test_html_renamed_to_jpg_is_rejected(): void
    {
        $this->upload($this->realFile('evil.jpg', '<html><script>alert(1)</script></html>'))
            ->assertStatus(422);

        Storage::disk('backgrounds')->assertMissing('evil.jpg');
    }

    public function test_image_bytes_under_an_html_name_are_rejected(): void
    {
        $this->upload($this->realFile('evil.html', $this->pngBytes() . '<script>alert(1)</script>'))
            ->assertStatus(422);

        Storage::disk('backgrounds')->assertMissing('evil.html');
    }

    public function test_non_admin_cannot_upload(): void
    {
        $user = User::createAccount([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password',
        ], ['active' => true]);

        $this->withHeader('Authorization', 'Bearer ' . JWTAuth::fromUser($user))
            ->post('/api/settings/backgrounds', [
                'background_image' => $this->realFile('sky.png', $this->pngBytes()),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }
}
