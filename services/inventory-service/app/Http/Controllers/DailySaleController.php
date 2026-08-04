<?php

namespace App\Http\Controllers;

use App\Application\DTOs\CreateDailySaleData;
use App\Application\DTOs\VerifiedToken;
use App\Application\UseCases\CancelDailySaleUseCase;
use App\Application\UseCases\ConfirmDailySaleUseCase;
use App\Application\UseCases\CreateDailySaleUseCase;
use App\Application\UseCases\GetDailySaleUseCase;
use App\Application\UseCases\ListDailySalesUseCase;
use App\Http\Requests\CancelDailySaleRequest;
use App\Http\Requests\StoreDailySaleRequest;
use App\Http\Resources\DailySaleResource;
use App\Http\Resources\DailySaleSummaryResource;
use App\Models\DailySale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class DailySaleController extends Controller
{
    public function index(Request $request, ListDailySalesUseCase $useCase): JsonResponse
    {
        $dailySales = $useCase->execute(
            dateFrom: $request->query('date_from') !== null ? (string) $request->query('date_from') : null,
            dateTo: $request->query('date_to') !== null ? (string) $request->query('date_to') : null,
            status: $request->query('status') !== null ? (string) $request->query('status') : null,
            perPage: (int) $request->query('per_page', 20),
        );

        return response()->json([
            'daily_sales' => DailySaleSummaryResource::collection($dailySales->items())->resolve($request),
            'meta' => [
                'current_page' => $dailySales->currentPage(),
                'per_page' => $dailySales->perPage(),
                'total' => $dailySales->total(),
                'last_page' => $dailySales->lastPage(),
            ],
        ]);
    }

    public function store(StoreDailySaleRequest $request, CreateDailySaleUseCase $useCase): JsonResponse
    {
        /** @var VerifiedToken $verified */
        $verified = $request->attributes->get('verified_token');
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            abort(422, 'Vui lòng chọn file CSV.');
        }

        $dailySale = $useCase->execute(new CreateDailySaleData(
            file: $file,
            salesDate: (string) $request->validated('sales_date'),
            createdBy: $verified->userId,
        ));

        return response()->json([
            'daily_sale' => DailySaleResource::make($dailySale)->resolve($request),
        ], 201);
    }

    public function show(DailySale $dailySale, GetDailySaleUseCase $useCase, Request $request): JsonResponse
    {
        return response()->json([
            'daily_sale' => DailySaleResource::make($useCase->execute($dailySale))->resolve($request),
        ]);
    }

    public function confirm(DailySale $dailySale, ConfirmDailySaleUseCase $useCase, Request $request): JsonResponse
    {
        /** @var VerifiedToken $verified */
        $verified = $request->attributes->get('verified_token');

        $confirmed = $useCase->execute($dailySale, $verified->userId);

        return response()->json([
            'daily_sale' => DailySaleResource::make($confirmed)->resolve($request),
        ]);
    }

    public function cancel(CancelDailySaleRequest $request, DailySale $dailySale, CancelDailySaleUseCase $useCase): JsonResponse
    {
        /** @var VerifiedToken $verified */
        $verified = $request->attributes->get('verified_token');

        $cancelled = $useCase->execute(
            dailySale: $dailySale,
            reason: (string) $request->validated('reason'),
            cancelledBy: $verified->userId
        );

        return response()->json([
            'daily_sale' => DailySaleResource::make($cancelled)->resolve($request),
        ]);
    }
}
