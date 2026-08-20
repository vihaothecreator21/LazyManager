<?php

namespace App\Http\Requests;

use App\Application\DTOs\CreateBorrowRecordData;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBorrowRecordRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sku_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrow_location' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku_id.required' => 'Vui lòng chọn SKU.',
            'quantity.required' => 'Vui lòng nhập số lượng mượn.',
            'quantity.integer' => 'Số lượng mượn phải là số nguyên.',
            'quantity.min' => 'Số lượng mượn phải lớn hơn 0.',
            'borrower_name.required' => 'Vui lòng nhập người mượn.',
            'borrow_location.required' => 'Vui lòng nhập nơi mượn.',
        ];
    }

    public function toData(): CreateBorrowRecordData
    {
        return new CreateBorrowRecordData(
            skuId: (int) $this->integer('sku_id'),
            quantity: (int) $this->integer('quantity'),
            borrowerName: trim((string) $this->input('borrower_name')),
            borrowLocation: trim((string) $this->input('borrow_location')),
            note: $this->filled('note') ? trim((string) $this->input('note')) : null,
        );
    }
}
