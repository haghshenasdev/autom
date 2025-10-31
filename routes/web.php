<?php

use App\Http\Controllers\ai\CategoryPredictor;
use App\Models\Task;
use Illuminate\Support\Carbon;
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

Route::middleware('auth')->group(function () {
    Route::get('/private-dl/{path}', function ($path) {
        if (!Storage::disk('private')->exists($path)) {
            abort(404);
        }

        $path = config('filesystems.disks.private.root') . DIRECTORY_SEPARATOR . $path;

        return response()->file($path);
    })->where('path', '.*');

    Route::get('/minutes-dl/{path}', function ($path) {
        if (!Storage::disk('private2')->exists($path)) {
            abort(404);
        }

        $path = config('filesystems.disks.private2.root') . DIRECTORY_SEPARATOR . $path;

        return response()->file($path);
    })->where('path', '.*');

    Route::get('/appendix-other-dl/{path}', function ($path) {
        if (!Storage::disk('private_appendix_other')->exists($path)) {
            abort(404);
        }

        $path = config('filesystems.disks.private_appendix_other.root') . DIRECTORY_SEPARATOR . $path;

        return response()->file($path);
    })->where('path', '.*');

    // گروه روت‌های نمایش محتوا
    Route::get('/private-show/{path}', function ($path) {
        if (!Storage::disk('private')->exists($path)) {
            abort(404);
        }

        $content = Storage::disk('private')->get($path);

        return response($content, 200)
            ->header('Content-Type', getMimeTypeFromExtension($path))
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
    })->where('path', '.*');

    Route::get('/appendix-other-show/{path}', function ($path) {
        if (!Storage::disk('private_appendix_other')->exists($path)) {
            abort(404);
        }

        $content = Storage::disk('private_appendix_other')->get($path);

        return response($content, 200)
            ->header('Content-Type', getMimeTypeFromExtension($path))
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
    })->where('path', '.*');

    function getMimeTypeFromExtension($filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $mimeTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'bmp'  => 'image/bmp',
            'webp' => 'image/webp',
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
            'csv'  => 'text/csv',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mp3'  => 'audio/mpeg',
            'mp4'  => 'video/mp4',
            'zip'  => 'application/zip',
            'rar'  => 'application/vnd.rar',
            // می‌تونی پسوندهای بیشتری اضافه کنی
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
});
Route::get('/login',function (){
    return redirect('admin');
})->name('login');

Route::get('/register',function (){
    return redirect('admin');
})->name('register');


Route::post('/dghdfkgjslikrltkiuwe/webhook',[\App\Http\Controllers\BaleBotController::class,'webhook'])->name('bale_webhook');
Route::get('/eeita',[\App\Http\Controllers\ReadChanel::class,'read']);


Route::get('so',function (){
    $obj = json_decode('{"update_id":149,"message":{"message_id":267,"from":{"id":1497344206,"is_bot":false,"first_name":"\u062d\u0642 \u0634\u0646\u0627\u0633","last_name":null,"username":"mhdev"},"date":1761947568,"chat":{"id":1497344206,"type":"private","username":"mhdev","first_name":"\u062d\u0642 \u0634\u0646\u0627\u0633"},"media_group_id":"5546907248048185631","document":{"file_id":"1497344206:-3798554410216448255:1:ce9db56cfaf1c694971dc4bcab4be39042810c9ccd4ad205","file_unique_id":null,"file_name":"IMG_20251101_010509.jpg","mime_type":"image\/jpeg","file_size":320219},"photo":[{"file_id":"1497344206:-3798554410216448255:1:ce9db56cfaf1c694971dc4bcab4be39042810c9ccd4ad205","file_unique_id":null,"width":1180,"height":1797,"file_size":320219}]}}');

    $mp = new \App\Http\Controllers\ai\MinutesParser();
    dd($obj->message);
//    $dp = $mp->parse($obj->message->caption);
//    dd($dp);
//    dd(\Morilog\Jalali\Jalalian::fromFormat('Y-m-d','1397-05-02')->toString());
});

