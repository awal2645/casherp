<?php

use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->prefix('hrm')->group(function () {
    Route::get('/attendance/status', [
        Modules\Essentials\Http\Controllers\AttendanceApiController::class,
        'status',
    ]);
    Route::post('/attendance/clock', [
        Modules\Essentials\Http\Controllers\AttendanceApiController::class,
        'clock',
    ]);
});
