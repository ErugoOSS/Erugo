<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Admin middleware handles auth
    }

    /**
     * Lowercase the email before the unique rule runs (issue #244).
     */
    protected function prepareForValidation(): void
    {
        User::normalizeRequestEmail($this);
    }

    public function rules(): array
    {
        $userId = $this->route('id');
        
        return [
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'name' => 'sometimes|string|max:255',
            'admin' => 'sometimes|boolean',
            'must_change_password' => 'sometimes|boolean',
        ];
    }
}
