<?php

namespace App\Http\Controllers;

use App\Application\DTOs\VerifiedToken;
use App\Application\UseCases\CreateBorrowRecordUseCase;
use App\Http\Requests\StoreBorrowRecordRequest;
use App\Http\Resources\BorrowRecordResource;
use Illuminate\Http\JsonResponse;

final class BorrowRecordController extends Controller
{
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
}
