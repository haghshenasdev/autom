<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect('admin');
});

Route::get('/private-dl/{path}',function ($path){
//    $path = request()->get('path');
    if (!Storage::disk('private')->exists($path)) {
        abort(404);
    }

    $path = config('filesystems.disks.private.root') . DIRECTORY_SEPARATOR . $path;

    return response()->file($path);
// for download
//    return Storage::disk('private')->download(
//        $path,basename($path),[
//            'Content-Length' => Storage::disk('private')->size($path)
//        ]
//    );
})->middleware('auth')->where('path', '.*');;

Route::get('/private-show/{path}',function ($path){
    if (!Storage::disk('private')->exists($path)) {
        abort(404);
    }

    $path = config('filesystems.disks.private.root') . DIRECTORY_SEPARATOR . $path;

    return response()->file($path,[
        'Content-Type' => mime_content_type($path),
    ]);
})->middleware('auth')->where('path', '.*');;

Route::get('/minutes-dl/{path}',function ($path){
//    $path = request()->get('path');
    if (!Storage::disk('private2')->exists($path)) {
        abort(404);
    }

    $path = config('filesystems.disks.private2.root') . DIRECTORY_SEPARATOR . $path;

    return response()->file($path);
// for download
//    return Storage::disk('private')->download(
//        $path,basename($path),[
//            'Content-Length' => Storage::disk('private')->size($path)
//        ]
//    );
})->middleware('auth')->where('path', '.*');


Route::get('/appendix-other-dl/{path}',function ($path){
//    $path = request()->get('path');
    if (!Storage::disk('private_appendix_other')->exists($path)) {
        abort(404);
    }

    $path = config('filesystems.disks.private_appendix_other.root') . DIRECTORY_SEPARATOR . $path;

    return response()->file($path);
})->middleware('auth')->where('path', '.*');

Route::get('/appendix-other-show/{path}',function ($path){
//    $path = request()->get('path');
    if (!Storage::disk('private_appendix_other')->exists($path)) {
        abort(404);
    }

    $path = config('filesystems.disks.private_appendix_other.root') . DIRECTORY_SEPARATOR . $path;

    return response()->file($path,[
        'Content-Type' => mime_content_type($path),
    ]);
})->middleware('auth')->where('path', '.*');

Route::get('/login',function (){
    return redirect('admin');
})->name('login');

Route::get('/register',function (){
    return redirect('admin');
})->name('register');


Route::post('/dghdfkgjslikrltkiuwe/webhook',[\App\Http\Controllers\BaleBotController::class,'webhook'])->name('bale_webhook');
Route::get('/eeita',[\App\Http\Controllers\ReadChanel::class,'read']);


Route::get('/test-s3', function () {
    try {
        $path = 'dhj/letters/1/1.jpg';
        if (!Storage::disk('private')->exists($path)) {
            abort(404);
        }

        // دریافت محتوای فایل
        $content = Storage::disk('private')->get($path);

        // تعیین نوع MIME (اختیاری)
        $mime = Storage::disk('private')->mimeType($path);

        // ارسال پاسخ به مرورگر
        return Response::make($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => '❌ اتصال ناموفق',
            'error' => $e->getMessage(),
        ]);
    }
});
