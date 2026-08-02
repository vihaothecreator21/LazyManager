<?php

namespace App\Http\Controllers;

use App\Application\DTOs\CreateSkuData;
use App\Application\DTOs\UpdateSkuData;
use App\Application\UseCases\CreateSkuUseCase;
use App\Application\UseCases\DeactivateSkuUseCase;
use App\Application\UseCases\UpdateSkuUseCase;
use App\Http\Requests\StoreSkuRequest;
use App\Http\Requests\UpdateSkuRequest;
use App\Http\Resources\ProductSkuResource;
use App\Models\Product;
use App\Models\ProductSku;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SkuController extends Controller
{
    public function store(StoreSkuRequest $request, Product $product, CreateSkuUseCase $useCase): JsonResponse
    {
        $sku = $useCase->execute($product, new CreateSkuData(
            skuCode: $request->string('sku_code')->toString(),
            size: $request->string('size')->toString(),
        ));

        return response()->json([
            'sku' => ProductSkuResource::make($sku)->resolve($request),
        ], 201);
    }

    public function update(UpdateSkuRequest $request, ProductSku $sku, UpdateSkuUseCase $useCase): JsonResponse
    {
        $sku = $useCase->execute($sku, new UpdateSkuData(
            skuCode: $request->has('sku_code') ? $request->string('sku_code')->toString() : null,
            size: $request->has('size') ? $request->string('size')->toString() : null,
        ));

        return response()->json([
            'sku' => ProductSkuResource::make($sku)->resolve($request),
        ]);
    }

    public function destroy(ProductSku $sku, DeactivateSkuUseCase $useCase, Request $request): JsonResponse
    {
        return response()->json([
            'sku' => ProductSkuResource::make($useCase->execute($sku))->resolve($request),
        ]);
    }
}
