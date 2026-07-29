<?php

namespace App\Http\Middleware;

use App\Application\DTOs\VerifiedToken;
use App\Domain\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ManagerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var VerifiedToken|null $verified */
        $verified = $request->attributes->get('verified_token');

        if ($verified === null || $verified->role !== UserRole::StoreManager) {
            return response()->json(['message' => 'Bạn không có quyền thực hiện thao tác này.'], 403);
        }

        return $next($request);
    }
}
