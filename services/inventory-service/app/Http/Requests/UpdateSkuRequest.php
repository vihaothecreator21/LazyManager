<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateSkuRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sku_code' => ['sometimes', 'required', 'string', 'max:64'],
            'size' => ['sometimes', 'required', 'string', 'max:64'],
        ];
    }
}
