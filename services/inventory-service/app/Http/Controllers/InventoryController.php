<?php

namespace App\Http\Controllers;

use App\Application\UseCases\ListInventoryBalancesUseCase;
use App\Application\UseCases\ListInventoryTransactionsUseCase;
use App\Http\Resources\InventoryBalanceResource;
use App\Http\Resources\InventoryTransactionResource;
use App\Models\ProductSku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryController extends Controller
{
    public function index(Request $request, ListInventoryBalancesUseCase $useCase): JsonResponse
    {
        $search = $request->query('search');
        $inventory = $useCase->execute(is_string($search) ? $search : null);

        return response()->json([
            'inventory' => InventoryBalanceResource::collection($inventory)->resolve($request),
        ]);
    }

    public function transactions(
        ProductSku $sku,
        ListInventoryTransactionsUseCase $useCase,
        Request $request,
    ): JsonResponse {
        return response()->json([
            'transactions' => InventoryTransactionResource::collection($useCase->execute($sku))->resolve($request),
        ]);
    }
}
