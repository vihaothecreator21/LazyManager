<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('csrf.double_submit');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('csrf.double_submit');

    Route::middleware('auth.jwt')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::get('/employees', [EmployeeController::class, 'index'])->middleware('role.manager');
        Route::get('/schedules', [ScheduleController::class, 'index']);
        Route::post('/schedules/assignments', [ScheduleController::class, 'storeAssignment'])
            ->middleware('csrf.double_submit');
        Route::delete('/schedules/assignments/{id}', [ScheduleController::class, 'destroyAssignment'])
            ->middleware('csrf.double_submit');
        Route::post('/schedules/day-offs', [ScheduleController::class, 'storeDayOff'])
            ->middleware('csrf.double_submit');
        Route::delete('/schedules/day-offs/{id}', [ScheduleController::class, 'destroyDayOff'])
            ->middleware('csrf.double_submit');

        Route::middleware(['csrf.double_submit', 'role.manager'])->group(function (): void {
            Route::post('/employees', [EmployeeController::class, 'store']);
            Route::put('/employees/{id}', [EmployeeController::class, 'update']);
            Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
        });
    });
});
