<?php

namespace App\Http\Requests;

use App\Application\DTOs\UpdateStockCountLineItemData;
use App\Application\DTOs\UpdateStockCountLinesData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateStockCountLinesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer', 'distinct'],
            'lines.*.actual_quantity' => ['required', 'integer', 'min:0'],
            'lines.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toData(): UpdateStockCountLinesData
    {
        return new UpdateStockCountLinesData(
            lines: array_map(
                fn (array $line): UpdateStockCountLineItemData => new UpdateStockCountLineItemData(
                    lineId: (int) $line['line_id'],
                    actualQuantity: (int) $line['actual_quantity'],
                    note: isset($line['note']) && $line['note'] !== null ? trim((string) $line['note']) : null,
                ),
                $this->validated('lines'),
            ),
        );
    }
}
