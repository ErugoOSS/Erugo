<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin middleware handles auth
    }

    public function rules(): array
    {
        return [
            'application_name' => 'sometimes|string|max:255',
            'login_message' => 'sometimes|nullable|string|max:500',
            'logo_width' => 'sometimes|integer|min:50|max:500',
            'css_primary_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'css_secondary_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'css_accent_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'css_accent_color_light' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'use_my_backgrounds' => 'sometimes|boolean',
            'background_slideshow_speed' => 'sometimes|integer|min:0',
            'show_powered_by' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'css_primary_color.regex' => 'The primary color must be a valid hex color (e.g., #FF5733).',
            'css_secondary_color.regex' => 'The secondary color must be a valid hex color (e.g., #FF5733).',
            'css_accent_color.regex' => 'The accent color must be a valid hex color (e.g., #FF5733).',
            'css_accent_color_light.regex' => 'The accent light color must be a valid hex color (e.g., #FF5733).',
        ];
    }
}
