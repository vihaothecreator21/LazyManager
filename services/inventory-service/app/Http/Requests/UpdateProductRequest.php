<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateProductRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'product_code' => ['sometimes', 'required', 'string', 'max:64'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
