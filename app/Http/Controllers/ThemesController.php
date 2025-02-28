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

    public function getThemes()
    {
        $themes = Theme::all();
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
