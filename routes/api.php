<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MobileApiController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:api');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('password', [AuthController::class, 'password']);

    Route::prefix('mobile/v1')->group(function () {
        Route::get('permissions', [MobileApiController::class, 'permissions']);

        Route::get('cartable', [MobileApiController::class, 'cartable']);
        Route::patch('cartable/{id}', [MobileApiController::class, 'cartableUpdate']);

        Route::get('referrals', [MobileApiController::class, 'referralIndex']);
        Route::post('referrals', [MobileApiController::class, 'referralStore']);
        Route::patch('referrals/{id}', [MobileApiController::class, 'referralUpdate']);

        Route::get('letters/{id}/timeline', [MobileApiController::class, 'timeline']);

        Route::get('{resource}/reference', [MobileApiController::class, 'reference']);

        Route::get('{resource}', [MobileApiController::class, 'index']);
        Route::post('{resource}', [MobileApiController::class, 'store']);
        Route::get('{resource}/{id}', [MobileApiController::class, 'show']);
        Route::match(['put','patch','post'], '{resource}/{id}', [MobileApiController::class, 'update']);
        Route::delete('{resource}/{id}', [MobileApiController::class, 'destroy']);
    });
});
