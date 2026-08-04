<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDailySaleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sales_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sales_date.required' => 'Vui lòng chọn ngày bán.',
            'sales_date.date_format' => 'Ngày bán không hợp lệ.',
            'sales_date.before_or_equal' => 'Ngày bán không được sau hôm nay.',
            'file.required' => 'Vui lòng chọn file CSV.',
            'file.file' => 'File tải lên không hợp lệ.',
            'file.mimes' => 'File phải là CSV.',
            'file.max' => 'File CSV không được vượt quá 2MB.',
        ];
    }
}
