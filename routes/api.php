<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MobileApiController;
use App\Http\Controllers\Api\MobileAiController;
use App\Http\Controllers\Api\ProfileController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:api');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('password', [AuthController::class, 'password']);
    Route::get('profile', [ProfileController::class, 'me']);
    Route::get('profile/avatar', [ProfileController::class, 'avatar']);
    Route::get('get_avatar/{filename}', [ProfileController::class, 'get_avatar'])->where('filename', '.*');

    Route::prefix('mobile/v1')->group(function () {
        Route::get('permissions', [MobileApiController::class, 'permissions']);
        Route::post('ai/minutes', [MobileAiController::class, 'minute']);
        Route::post('ai/letters', [MobileAiController::class, 'letter']);
        Route::post('analyze-title', [MobileAiController::class, 'analyzeTitle']);

        Route::get('files/{resource}/{id}', [MobileApiController::class, 'files']);
        Route::get('files/{resource}/{id}/{fileKey}', [MobileApiController::class, 'file']);

        Route::get('cartable', [MobileApiController::class, 'cartable']);
        Route::patch('cartable/{id}', [MobileApiController::class, 'cartableUpdate']);

        Route::get('referrals', [MobileApiController::class, 'referralIndex']);
        Route::post('referrals', [MobileApiController::class, 'referralStore']);
        Route::patch('referrals/{id}', [MobileApiController::class, 'referralUpdate']);

        Route::get('letters/{id}/timeline', [MobileApiController::class, 'timeline']);
        Route::get('projects/{id}/children', [MobileApiController::class, 'projectChildren']);
        Route::get('projects/{id}/report', [MobileApiController::class, 'projectReport']);
        Route::get('reports/{resource}', [MobileApiController::class, 'reports']);
        Route::get('calendar/tasks', [MobileApiController::class, 'calendar']);
        Route::get('notifications', [MobileApiController::class, 'notifications']);
        Route::patch('notifications/{id}/read', [MobileApiController::class, 'notificationRead']);

        Route::get('{resource}/reference', [MobileApiController::class, 'reference']);

        Route::get('{resource}', [MobileApiController::class, 'index']);
        Route::post('{resource}', [MobileApiController::class, 'store']);
        Route::get('{resource}/{id}', [MobileApiController::class, 'show']);
        Route::match(['put','patch','post'], '{resource}/{id}', [MobileApiController::class, 'update']);
        Route::delete('{resource}/{id}', [MobileApiController::class, 'destroy']);
    });
});
