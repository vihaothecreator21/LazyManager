<?php

namespace App\Http\Controllers;

use App\Application\DTOs\VerifiedToken;
use App\Application\UseCases\CreateBorrowRecordUseCase;
use App\Application\UseCases\GetBorrowRecordUseCase;
use App\Application\UseCases\ListBorrowRecordsUseCase;
use App\Http\Requests\StoreBorrowRecordRequest;
use App\Http\Resources\BorrowRecordResource;
use App\Models\BorrowRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BorrowRecordController extends Controller
{
    public function index(Request $request, ListBorrowRecordsUseCase $useCase): JsonResponse
    {
        return response()->json([
            'borrow_records' => BorrowRecordResource::collection($useCase->execute(
                status: $request->query('status') !== null ? (string) $request->query('status') : null,
                search: $request->query('search') !== null ? (string) $request->query('search') : null,
            ))->resolve($request),
        ]);
    }

    public function store(StoreBorrowRecordRequest $request, CreateBorrowRecordUseCase $useCase): JsonResponse
    {
        /** @var VerifiedToken $verified */
        $verified = $request->attributes->get('verified_token');

        return response()->json([
            'borrow_record' => BorrowRecordResource::make(
                $useCase->execute($request->toData(), $verified->userId)
            )->resolve($request),
        ], 201);
    }

    public function show(BorrowRecord $borrowRecord, GetBorrowRecordUseCase $useCase, Request $request): JsonResponse
    {
        return response()->json([
            'borrow_record' => BorrowRecordResource::make($useCase->execute($borrowRecord))->resolve($request),
        ]);
    }
}
