<?php

use App\Application\DTOs\VerifiedToken;
use App\Http\Controllers\ProductController;
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

    Route::apiResource('products', ProductController::class);
});
