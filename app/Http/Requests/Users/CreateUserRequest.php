<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
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
        return [
            'email' => 'required|email|unique:users,email',
            'name' => 'required|string|max:255',
            'admin' => 'sometimes|boolean',
        ];
    }
}
