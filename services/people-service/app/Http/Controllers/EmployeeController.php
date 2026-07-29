<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stub controller cho employee mutations.
 * Middleware auth.jwt + role.manager được kiểm tra trước khi vào đây.
 * Implement đầy đủ ở UC tiếp theo.
 */
final class EmployeeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented.'], 501);
    }
}
