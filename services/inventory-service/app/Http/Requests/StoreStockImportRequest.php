<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreStockImportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn file CSV.',
            'file.file' => 'File tải lên không hợp lệ.',
            'file.mimes' => 'File phải là CSV.',
            'file.max' => 'File CSV không được vượt quá 2MB.',
        ];
    }
}
