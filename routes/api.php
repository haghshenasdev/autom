<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [ProfileController::class, 'me']);

    Route::get(
        '/me/avatar',
        [ProfileController::class, 'avatar']
    );

    Route::get(
        '/get_avatar/{filename}',
        [ProfileController::class, 'get_avatar']
    );

    Route::get('/appendix-other-show/{path}', function ($path) {
        if (!Storage::disk('private_appendix_other')->exists($path)) {
            abort(404);
        }

        $content = Storage::disk('private_appendix_other')->get($path);

        return response($content, 200)
            ->header('Content-Type', getMimeTypeFromExtension($path))
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
    })->where('path', '.*');

});
