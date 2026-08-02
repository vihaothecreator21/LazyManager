<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSkuRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sku_code' => ['required', 'string', 'max:64'],
            'size' => ['required', 'string', 'max:64'],
        ];
    }
}
