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
            'settings.*.value' => 'string|nullable|max:255',
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
        $originalFilename = $logo->getClientOriginalName();
        
        // Sanitize the filename to prevent path traversal attacks
        $sanitizedFilename = FileHelper::sanitizeFilename($originalFilename);
        
        // Store the logo using the sanitized filename
        Storage::disk('public')->put($sanitizedFilename, file_get_contents($logo));

        return response()->json([
            'status' => 'success',
            'message' => 'Logo updated successfully',
            'data' => [
                'filename' => $sanitizedFilename,
                'original_filename' => $originalFilename,
            ]
        ]);
    }
}
