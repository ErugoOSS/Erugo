<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin middleware handles auth
    }

    public function rules(): array
    {
        return [
            'application_url' => 'sometimes|url|max:255',
            'default_language' => 'sometimes|string|in:en,de,fr,it,nl,pt,pt-BR',
            'show_language_selector' => 'sometimes|boolean',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Strip trailing slash from URL if present
        if ($this->has('application_url')) {
            $url = $this->input('application_url');
            if ($url && str_ends_with($url, '/')) {
                $this->merge(['application_url' => rtrim($url, '/')]);
            }
        }
    }
}
