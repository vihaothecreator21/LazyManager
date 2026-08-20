<?php

namespace App\Http\Controllers;

use App\Application\DTOs\CreateStockImportData;
use App\Application\DTOs\VerifiedToken;
use App\Application\UseCases\ConfirmStockImportUseCase;
use App\Application\UseCases\GetStockImportPreviewUseCase;
use App\Application\UseCases\PreviewStockImportUseCase;
use App\Http\Requests\StoreStockImportRequest;
use App\Http\Resources\StockImportResource;
use App\Models\StockImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class StockImportController extends Controller
{
    public function store(StoreStockImportRequest $request, PreviewStockImportUseCase $useCase): JsonResponse
    {
        /** @var VerifiedToken $verified */
        $verified = $request->attributes->get('verified_token');
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            abort(422, 'Vui lòng chọn file CSV.');
        }

        $stockImport = $useCase->execute(new CreateStockImportData(
            file: $file,
            createdBy: $verified->userId,
        ));

        return response()->json([
            'stock_import' => StockImportResource::make($stockImport)->resolve($request),
        ], 201);
    }

    public function preview(
        StockImport $stockImport,
        GetStockImportPreviewUseCase $useCase,
        Request $request,
    ): JsonResponse {
        return response()->json([
            'stock_import' => StockImportResource::make($useCase->execute($stockImport))->resolve($request),
        ]);
    }

    public function confirm(
        StockImport $stockImport,
        ConfirmStockImportUseCase $useCase,
        Request $request,
    ): JsonResponse {
        /** @var VerifiedToken $verified */
        $verified = $request->attributes->get('verified_token');

        return response()->json([
            'stock_import' => StockImportResource::make($useCase->execute($stockImport, $verified->userId))->resolve($request),
        ]);
    }
}
