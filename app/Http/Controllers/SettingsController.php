<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Utils\FileHelper;
class SettingsController extends Controller
{
    public function write(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|max:255',
            'settings.*.value' => 'string|nullable|max:65535',
        ]);

        $errors = [];
        $savedSettings = [];

        foreach ($request->settings as $settingData) {
            try {
                $setting = Setting::where('key', $settingData['key'])->first();
                
                if (!$setting) {
                    throw new \Exception('Setting does not exist');
                }

                $setting->key = $settingData['key'];
                $setting->previous_value = $setting->value;
                $setting->value = $settingData['value'];
                $setting->save();

                $savedSettings[] = $setting;
            } catch (\Exception $e) {
                $errors[] = [
                    'key' => $settingData['key'],
                    'error' => $e->getMessage()
                ];
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Some settings could not be saved',
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Settings saved successfully',
            'data' => [
                'settings' => $savedSettings,
            ]
        ]);
    }

    public function read(Request $request, $key)
    {
        $setting = Setting::where('key', $key)->first();
        if (!$setting) {
            return response()->json([
                'status' => 'error',
                'message' => 'Setting not found',
            ], 404);
        }
        return response()->json([
            'status' => 'success',
            'data' => [
                'setting' => $setting,
            ]
        ]);
    }

    public function readGroup(Request $request, $group)
    {

        $query = Setting::query();

        if (str_ends_with($group, '.*')) {
            // For patterns like "general.*"
            $baseGroup = rtrim($group, '.*');
            $query->where(function ($q) use ($baseGroup) {
                $q->where('group', $baseGroup)  // Matches exact base group
                    ->orWhere('group', 'LIKE', $baseGroup . '.%');  // Matches anything with baseGroup.
            });
        } else {
            // For exact matches like "general" or "general.shares"
            $query->where('group', $group);
        }

        $settings = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'settings' => $settings,
            ]
        ]);
    }

    public function writeLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,svg|max:2048',
        ]);

        $logo = $request->file('logo');
        
        // Store the logo as logo.png in public/images (overwriting any existing logo)
        $logoPath = public_path('images/logo.png');
        file_put_contents($logoPath, file_get_contents($logo));

        return response()->json([
            'status' => 'success',
            'message' => 'Logo updated successfully',
        ]);
    }

    public function deleteLogo()
    {
        $defaultLogoPath = public_path('images/_default-logo.png');
        $logoPath = public_path('images/logo.png');
        
        if (!file_exists($defaultLogoPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Default logo not found',
            ], 404);
        }
        
        // Copy the default logo to restore it
        copy($defaultLogoPath, $logoPath);

        // Update the logo setting to show the default logo filename
        $logoSetting = Setting::where('key', 'logo')->where('group', 'ui.logo')->first();
        if ($logoSetting) {
            $logoSetting->previous_value = $logoSetting->value;
            $logoSetting->value = 'erugo-logo.png';
            $logoSetting->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Logo reset to default successfully',
        ]);
    }

    public function writeFavicon(Request $request)
    {
        $request->validate([
            'favicon' => 'required|file|mimes:png,svg|max:1024',
        ]);

        $favicon = $request->file('favicon');
        $extension = strtolower($favicon->getClientOriginalExtension());
        
        // Always store as favicon.png or favicon.svg
        $filename = 'favicon.' . $extension;
        
        // Delete any existing favicon files first
        if (Storage::disk('public')->exists('favicon.png')) {
            Storage::disk('public')->delete('favicon.png');
        }
        if (Storage::disk('public')->exists('favicon.svg')) {
            Storage::disk('public')->delete('favicon.svg');
        }
        
        // Store the new favicon
        Storage::disk('public')->put($filename, file_get_contents($favicon));

        return response()->json([
            'status' => 'success',
            'message' => 'Favicon updated successfully',
            'data' => [
                'filename' => $filename,
            ]
        ]);
    }

    public function deleteFavicon()
    {
        $deleted = false;
        
        if (Storage::disk('public')->exists('favicon.png')) {
            Storage::disk('public')->delete('favicon.png');
            $deleted = true;
        }
        if (Storage::disk('public')->exists('favicon.svg')) {
            Storage::disk('public')->delete('favicon.svg');
            $deleted = true;
        }

        return response()->json([
            'status' => 'success',
            'message' => $deleted ? 'Favicon deleted successfully' : 'No custom favicon to delete',
        ]);
    }

    public function getFavicon()
    {
        // Check for custom favicon (PNG first, then SVG)
        if (Storage::disk('public')->exists('favicon.png')) {
            $path = Storage::disk('public')->path('favicon.png');
            return response()->file($path, ['Content-Type' => 'image/png']);
        }
        
        if (Storage::disk('public')->exists('favicon.svg')) {
            $path = Storage::disk('public')->path('favicon.svg');
            return response()->file($path, ['Content-Type' => 'image/svg+xml']);
        }
        
        // Fall back to default icon.svg
        $defaultPath = public_path('icon.svg');
        if (file_exists($defaultPath)) {
            return response()->file($defaultPath, ['Content-Type' => 'image/svg+xml']);
        }
        
        abort(404);
    }

    public function hasFavicon()
    {
        $hasCustomFavicon = Storage::disk('public')->exists('favicon.png') || 
                           Storage::disk('public')->exists('favicon.svg');
        
        $filename = null;
        if (Storage::disk('public')->exists('favicon.png')) {
            $filename = 'favicon.png';
        } elseif (Storage::disk('public')->exists('favicon.svg')) {
            $filename = 'favicon.svg';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'has_custom_favicon' => $hasCustomFavicon,
                'filename' => $filename,
            ]
        ]);
    }
}
