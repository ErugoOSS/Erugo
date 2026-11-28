<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Theme;

class ThemesController extends Controller
{
    public function saveTheme(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'theme' => ['required', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $themeConfig = $request->input('theme');
        $themeName = $request->input('name');

        $theme = Theme::where('name', $themeName)->first();
        if ($theme) {
            $theme->theme = $themeConfig;
            $theme->save();
        } else {
            $theme = Theme::create([
                'name' => $themeName,
                'theme' => $themeConfig,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Theme saved successfully',
            'data' => $theme,
        ]);
    }

    public function installCustomTheme(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:512'], // 512 KB limit
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $themeName = $request->input('name');
        $themeFile = $request->file('file');

        // Verify file content is actually JSON (not just a renamed file)
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($themeFile->getRealPath());
        if (!in_array($realMime, ['application/json', 'text/plain'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Theme file must be a JSON file',
            ], 422);
        }

        $raw = file_get_contents($themeFile->getRealPath());
        $themeData = json_decode($raw, true);

        if (!is_array($themeData)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid theme file: must be a JSON object',
            ], 422);
        }

        $theme = Theme::where('name', $themeName)->first();
        if ($theme) {
            $themeName = $themeName . ' (custom)';
        }

        $theme = Theme::create([
            'name' => $themeName,
            'category' => 'custom',
            'theme' => $themeData,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Theme installed successfully',
            'data' => $theme,
        ]);
    }

    public function getThemes()
    {
        $themes = Theme::orderBy('category', 'desc')->orderBy('bundled', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'message' => 'Themes fetched successfully',
            'data' => [
                'themes' => $themes,
            ]
        ]);
    }

    public function deleteTheme(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        $theme = Theme::where('name', $request->input('name'))->first();
        if ($theme->active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete active theme',
            ], 400);
        }
        $theme->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Theme deleted successfully',
        ]);
    }

    public function setActiveTheme(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'data' => [
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        //find current active theme and set it to inactive
        $currentActiveTheme = Theme::where('active', true)->first();
        if ($currentActiveTheme) {
            $currentActiveTheme->active = false;
            $currentActiveTheme->save();
        }

        $theme = Theme::where('name', $request->input('name'))->first();
        $theme->active = true;
        $theme->save();



        return response()->json([
            'status' => 'success',
            'message' => 'Theme set as active successfully',
        ]);
    }

    public function getActiveTheme()
    {
        $theme = Theme::where('active', true)->first();
        return response()->json([
            'status' => 'success',
            'message' => 'Active theme fetched successfully',
            'data' => [
                'theme' => $theme,
            ]
        ]);
    }
}
