<?php

namespace App\Http\Requests;

use App\Domain\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreScheduleDayOffRequest extends FormRequest
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
            'off_date' => ['required', 'date_format:Y-m-d'],
            'employee_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('status', UserStatus::Active->value),
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
