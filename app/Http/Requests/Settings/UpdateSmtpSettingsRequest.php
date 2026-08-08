<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSmtpSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin middleware handles auth
    }

    public function rules(): array
    {
        return [
            'smtp_host' => 'sometimes|nullable|string|max:255',
            'smtp_port' => 'sometimes|nullable|integer|min:1|max:65535',
            'smtp_encryption' => 'sometimes|in:none,tls,ssl',
            'smtp_username' => 'sometimes|nullable|string|max:255',
            'smtp_password' => 'sometimes|nullable|string|max:255',
            'smtp_sender_name' => 'sometimes|nullable|string|max:255',
            'smtp_sender_address' => 'sometimes|nullable|email|max:255',
        ];
    }
}
