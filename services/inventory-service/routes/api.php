<?php

use App\Application\DTOs\VerifiedToken;
use App\Http\Controllers\BorrowRecordController;
use App\Http\Controllers\DailySaleController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SkuController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\StockImportController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth.jwt', 'csrf.double_submit'])->group(function (): void {
    Route::get('/auth/me', function (): JsonResponse {
        /** @var VerifiedToken $verified */
        $verified = request()->attributes->get('verified_token');

        return response()->json([
            'user' => [
                'id' => $verified->userId,
                'role' => $verified->role->value,
            ],
        ]);
    });

    Route::post('/auth/probe', function (): JsonResponse {
        /** @var VerifiedToken $verified */
        $verified = request()->attributes->get('verified_token');

        return response()->json([
            'user' => [
                'id' => $verified->userId,
                'role' => $verified->role->value,
            ],
        ]);
    });

    Route::post('/products/{product}/skus', [SkuController::class, 'store']);
    Route::put('/skus/{sku}', [SkuController::class, 'update']);
    Route::delete('/skus/{sku}', [SkuController::class, 'destroy']);
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/{sku}/transactions', [InventoryController::class, 'transactions'])->withTrashed();
    Route::post('/stock-imports', [StockImportController::class, 'store']);
    Route::get('/stock-imports/{stockImport}/preview', [StockImportController::class, 'preview']);
    Route::post('/stock-imports/{stockImport}/confirm', [StockImportController::class, 'confirm']);
    Route::get('/daily-sales', [DailySaleController::class, 'index']);
    Route::post('/daily-sales', [DailySaleController::class, 'store']);
    Route::get('/daily-sales/{dailySale}', [DailySaleController::class, 'show']);
    Route::post('/daily-sales/{dailySale}/confirm', [DailySaleController::class, 'confirm']);
    Route::post('/daily-sales/{dailySale}/cancel', [DailySaleController::class, 'cancel']);
    Route::get('/borrow-records', [BorrowRecordController::class, 'index']);
    Route::post('/borrow-records', [BorrowRecordController::class, 'store']);
    Route::get('/borrow-records/{borrowRecord}', [BorrowRecordController::class, 'show']);
    Route::post('/borrow-records/{borrowRecord}/return', [BorrowRecordController::class, 'returnRecord']);
    Route::get('/stock-counts', [StockCountController::class, 'index']);
    Route::post('/stock-counts', [StockCountController::class, 'store']);
    Route::get('/stock-counts/{stockCount}', [StockCountController::class, 'show']);
    Route::get('/stock-counts/{stockCount}/export-csv', [StockCountController::class, 'exportCsv']);
    Route::put('/stock-counts/{stockCount}/lines', [StockCountController::class, 'updateLines']);
    Route::apiResource('products', ProductController::class);
});
