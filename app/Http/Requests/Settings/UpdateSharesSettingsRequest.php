<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSharesSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin middleware handles auth
    }

    public function rules(): array
    {
        return [
            'max_share_size' => 'sometimes|integer|min:1',
            'max_share_size_unit' => 'sometimes|in:MB,GB,TB',
            'default_expiry_time' => 'sometimes|integer|min:1',
            'max_expiry_time' => 'sometimes|integer|min:1',
            'expiry_warning_days' => 'sometimes|integer|min:0',
            'deletion_warning_days' => 'sometimes|integer|min:0',
            'clean_files_after_days' => 'sometimes|integer|min:1',
            'allow_reverse_shares' => 'sometimes|boolean',
            'share_url_mode' => 'sometimes|in:haiku,pattern',
            'share_url_pattern' => 'nullable|string|max:255',
            'default_upload_mode' => 'sometimes|in:chunked,direct',
            'allow_direct_uploads' => 'sometimes|boolean',
            'allow_chunked_uploads' => 'sometimes|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Cross-field validation: max_expiry >= default_expiry
            $default = $this->input('default_expiry_time');
            $max = $this->input('max_expiry_time');
            
            if ($default !== null && $max !== null && $max < $default) {
                $validator->errors()->add(
                    'max_expiry_time',
                    'Max expiry time must be greater than or equal to default expiry time.'
                );
            }
        });
    }
}
