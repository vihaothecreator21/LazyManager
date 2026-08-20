<?php

namespace App\Models;

use App\Domain\Enums\BorrowRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BorrowRecord extends Model
{
    protected $fillable = [
        'sku_id',
        'quantity',
        'status',
        'borrower_name',
        'borrow_location',
        'note',
        'return_note',
        'created_by',
        'returned_by',
        'borrowed_at',
        'returned_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'status' => BorrowRecordStatus::class,
        'borrowed_at' => 'datetime',
        'returned_at' => 'datetime',
        'created_by' => 'integer',
        'returned_by' => 'integer',
    ];

    /** withTrashed: borrow record phải hiển thị SKU kể cả khi SKU bị soft-delete sau lúc cho mượn. */
    public function sku(): BelongsTo
    {
        return $this->belongsTo(ProductSku::class, 'sku_id')
            ->withTrashed();
    }
}
