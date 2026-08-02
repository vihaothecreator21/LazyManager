<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreProductRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'product_code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
