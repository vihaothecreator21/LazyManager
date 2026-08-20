<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CancelDailySaleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do hủy phiếu bán.',
            'reason.string' => 'Lý do hủy không hợp lệ.',
            'reason.max' => 'Lý do hủy không được vượt quá 255 ký tự.',
        ];
    }
}
