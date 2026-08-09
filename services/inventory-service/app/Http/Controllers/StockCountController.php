<?php

namespace App\Http\Controllers;

use App\Application\UseCases\CreateStockCountUseCase;
use App\Application\UseCases\ListStockCountsUseCase;
use App\Http\Requests\StoreStockCountRequest;
use App\Http\Resources\StockCountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
