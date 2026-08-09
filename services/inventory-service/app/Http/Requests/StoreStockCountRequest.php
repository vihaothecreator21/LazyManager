<?php

namespace App\Http\Requests;

use App\Application\DTOs\CreateStockCountData;
use App\Application\DTOs\VerifiedToken;
use Illuminate\Foundation\Http\FormRequest;

final class StoreStockCountRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'count_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function toData(): CreateStockCountData
    {
        /** @var VerifiedToken $verified */
        $verified = $this->attributes->get('verified_token');

        return new CreateStockCountData(
            name: $this->filled('name') ? trim((string) $this->input('name')) : null,
            countDate: $this->filled('count_date') ? (string) $this->input('count_date') : null,
            createdBy: $verified->userId,
        );
    }
}
