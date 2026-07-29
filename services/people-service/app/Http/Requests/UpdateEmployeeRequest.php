<?php

namespace App\Http\Requests;

use App\Domain\Enums\UserRole;
use App\Domain\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore((int) $this->route('id')),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'status' => ['sometimes', 'required', Rule::enum(UserStatus::class)],
        ];
    }
}
