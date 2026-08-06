<?php

namespace App\Http\Requests;

use App\Application\DTOs\ReturnBorrowRecordData;
use Illuminate\Foundation\Http\FormRequest;

final class ReturnBorrowRecordRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'return_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): ReturnBorrowRecordData
    {
        return new ReturnBorrowRecordData(
            returnNote: $this->filled('return_note') ? trim((string) $this->input('return_note')) : null,
        );
    }
}
