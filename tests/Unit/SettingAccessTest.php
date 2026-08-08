<?php

namespace Tests\Unit;

use App\Models\User;
use App\Settings\SettingAccess;
use App\Settings\SettingVisibility;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SettingAccessTest extends TestCase
{
    public function test_map_keys_exactly_match_settings_seeder(): void
    {
        $seederKeys = $this->keysFromSettingsSeeder();
        $mapKeys = SettingAccess::keys();

        sort($seederKeys);
        sort($mapKeys);

        $this->assertSame(
            $seederKeys,
            $mapKeys,
            'SettingAccess::MAP must list exactly the keys seeded by SettingsSeeder'
        );
    }

    public function test_smtp_and_licence_keys_are_secret(): void
    {
        $secretKeys = [
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'smtp_sender_name',
            'smtp_sender_address',
            'licence',
        ];

        foreach ($secretKeys as $key) {
            $this->assertSame(
                SettingVisibility::Secret,
                SettingAccess::visibility($key),
                "Expected {$key} to be secret"
            );
        }
    }

    public function test_unknown_keys_default_to_admin(): void
    {
        $this->assertSame(
            SettingVisibility::Admin,
            SettingAccess::visibility('totally_unknown_setting_key')
        );
    }

    public function test_non_admin_cannot_read_admin_keys(): void
    {
        $user = $this->userWithAdmin(false);

        $this->assertFalse(SettingAccess::canRead($user, 'smtp_password'));
        $this->assertFalse(SettingAccess::canRead($user, 'licence'));
        $this->assertTrue(SettingAccess::canRead($user, 'max_share_size'));
    }

    public function test_admin_can_read_all_keys_including_unknown(): void
    {
        $user = $this->userWithAdmin(true);

        $this->assertTrue(SettingAccess::canRead($user, 'smtp_password'));
        $this->assertTrue(SettingAccess::canRead($user, 'licence'));
        $this->assertTrue(SettingAccess::canRead($user, 'totally_unknown_setting_key'));
    }

    public function test_filter_readable_strips_admin_keys_for_non_admins(): void
    {
        $user = $this->userWithAdmin(false);
        $settings = new Collection([
            (object) ['key' => 'max_share_size', 'value' => '2'],
            (object) ['key' => 'smtp_password', 'value' => 'secret'],
            (object) ['key' => 'licence', 'value' => 'abc'],
        ]);

        $filtered = SettingAccess::filterReadable($user, $settings);

        $this->assertSame(['max_share_size'], $filtered->pluck('key')->all());
    }

    /**
     * admin is not mass assignable, so it has to be set directly.
     */
    private function userWithAdmin(bool $admin): User
    {
        $user = new User();
        $user->admin = $admin;

        return $user;
    }

    /**
     * @return list<string>
     */
    private function keysFromSettingsSeeder(): array
    {
        $path = dirname(__DIR__, 2) . '/database/seeders/SettingsSeeder.php';
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, 'Unable to read SettingsSeeder.php');

        preg_match_all("/'key'\\s*=>\\s*'([^']+)'/", $contents, $matches);

        $keys = $matches[1] ?? [];
        $this->assertNotEmpty($keys, 'No setting keys found in SettingsSeeder.php');

        return array_values(array_unique($keys));
    }
}
