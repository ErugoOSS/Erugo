<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Theme;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
        ]);

        // load themes.sql from the root of the project
        $sql = file_get_contents(base_path('themes.sql'));
        //run the query, ignore errors
        DB::unprepared($sql);

        // Repair: Fix databases where multiple themes are active (caused by themes.sql bug)
        // If Erugo 2026 is active alongside another theme, deactivate Erugo 2026
        $activeThemes = Theme::where('active', true)->get();
        if ($activeThemes->count() > 1 && $activeThemes->contains('name', 'Erugo 2026')) {
            Theme::where('name', 'Erugo 2026')->update(['active' => false]);
        }

        // Set default theme for fresh installs only
        // If no theme is currently active, set Erugo 2026 as the default
        if (!Theme::where('active', true)->exists()) {
            Theme::where('name', 'Erugo 2026')->update(['active' => true]);
        }
    }
}
