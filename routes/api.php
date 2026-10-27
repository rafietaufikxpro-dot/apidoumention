<?php

use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\RateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.key', 'throttle:60,1'])->group(function () {
    Route::get('rate', [RateController::class, 'show']);

    Route::get('alerts', [AlertController::class, 'index']);
    Route::post('alerts', [AlertController::class, 'store']);
    Route::delete('alerts/{alert}', [AlertController::class, 'destroy']);
    Route::post('alerts/check', [AlertController::class, 'check']);
});
