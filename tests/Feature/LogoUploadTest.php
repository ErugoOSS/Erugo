<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

// Laravel 12 stopped the image rule accepting SVG. Logos have always taken
// PNG or SVG, so the rule says image:allow_svg.
class LogoUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    private function upload(string $name, string $contents)
    {
        $admin = User::createAccount([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
        ], ['active' => true, 'admin' => true]);

        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, $contents);

        return $this->withHeader('Authorization', 'Bearer ' . JWTAuth::fromUser($admin))
            ->post('/api/settings/logo', ['logo' => new UploadedFile($path, $name, null, null, true)], ['Accept' => 'application/json']);
    }

    public function test_svg_logo_is_accepted(): void
    {
        $this->upload('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>')
            ->assertOk();
    }

    public function test_png_logo_is_accepted(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $this->upload('logo.png', $png)->assertOk();
    }

    public function test_text_file_is_rejected(): void
    {
        $this->upload('logo.png', 'not an image')->assertStatus(422);
    }
}
