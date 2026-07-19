<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', function (): array {
    return [
        'status' => 'ok',
        'service' => 'inventory-service',
    ];
});

Route::get('/ready', function (): JsonResponse {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ready',
            'service' => 'inventory-service',
            'database' => 'connected',
        ]);
    } catch (\Throwable) {
        return response()->json([
            'status' => 'not_ready',
            'service' => 'inventory-service',
            'database' => 'disconnected',
        ], 503);
    }
});
