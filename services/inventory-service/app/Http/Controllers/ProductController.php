<?php

namespace App\Http\Controllers;

use App\Application\DTOs\CreateProductData;
use App\Application\DTOs\UpdateProductData;
use App\Application\UseCases\CreateProductUseCase;
use App\Application\UseCases\DeactivateProductUseCase;
use App\Application\UseCases\GetProductUseCase;
use App\Application\UseCases\ListProductsUseCase;
use App\Application\UseCases\UpdateProductUseCase;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductController extends Controller
{
    public function index(Request $request, ListProductsUseCase $useCase): JsonResponse
    {
        $search = $request->query('search');
        $products = $useCase->execute(is_string($search) ? $search : null);

        return response()->json([
            'products' => ProductResource::collection($products)->resolve($request),
        ]);
    }

    public function store(StoreProductRequest $request, CreateProductUseCase $useCase): JsonResponse
    {
        $product = $useCase->execute(new CreateProductData(
            productCode: $request->string('product_code')->toString(),
            name: $request->string('name')->toString(),
        ));

        return response()->json([
            'product' => ProductResource::make($product)->resolve($request),
        ], 201);
    }

    public function show(Product $product, GetProductUseCase $useCase, Request $request): JsonResponse
    {
        return response()->json([
            'product' => ProductResource::make($useCase->execute($product))->resolve($request),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProductUseCase $useCase): JsonResponse
    {
        $product = $useCase->execute($product, new UpdateProductData(
            productCode: $request->has('product_code') ? $request->string('product_code')->toString() : null,
            name: $request->has('name') ? $request->string('name')->toString() : null,
        ));

        return response()->json([
            'product' => ProductResource::make($product)->resolve($request),
        ]);
    }

    public function destroy(Product $product, DeactivateProductUseCase $useCase, Request $request): JsonResponse
    {
        return response()->json([
            'product' => ProductResource::make($useCase->execute($product))->resolve($request),
        ]);
    }
}
