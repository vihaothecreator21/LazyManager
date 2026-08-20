<?php

namespace App\Http\Controllers;

use App\Application\UseCases\CreateStockCountUseCase;
use App\Application\UseCases\ExportStockCountCsvUseCase;
use App\Application\UseCases\GetStockCountUseCase;
use App\Application\UseCases\ListStockCountsUseCase;
use App\Application\UseCases\UpdateStockCountActualsUseCase;
use App\Http\Requests\StoreStockCountRequest;
use App\Http\Requests\UpdateStockCountLinesRequest;
use App\Http\Resources\StockCountResource;
use App\Models\StockCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StockCountController extends Controller
{
    public function index(Request $request, ListStockCountsUseCase $useCase): JsonResponse
    {
        return response()->json([
            'stock_counts' => StockCountResource::collection($useCase->execute())->resolve($request),
        ]);
    }

    public function store(StoreStockCountRequest $request, CreateStockCountUseCase $useCase): JsonResponse
    {
        return response()->json([
            'stock_count' => StockCountResource::make($useCase->execute($request->toData()))->resolve($request),
        ], 201);
    }

    public function show(StockCount $stockCount, GetStockCountUseCase $useCase, Request $request): JsonResponse
    {
        return response()->json([
            'stock_count' => StockCountResource::make($useCase->execute($stockCount))->resolve($request),
        ]);
    }

    public function exportCsv(StockCount $stockCount, ExportStockCountCsvUseCase $useCase): Response
    {
        return response($useCase->execute($stockCount), 200)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', "attachment; filename=\"stock-count-{$stockCount->id}.csv\"");
    }

    public function updateLines(
        UpdateStockCountLinesRequest $request,
        StockCount $stockCount,
        UpdateStockCountActualsUseCase $useCase,
    ): JsonResponse {
        return response()->json([
            'stock_count' => StockCountResource::make(
                $useCase->execute($stockCount, $request->toData())
            )->resolve($request),
        ]);
    }
}
