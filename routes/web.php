<?php

use App\Http\Controllers\ai\CategoryPredictor;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\Jalalian;

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
//$tst = null;
//    $englishDigits = ['0','1','2','3','4','5','6','7','8','9'];
//    $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
//    $converted = str_replace($persianDigits, $englishDigits, '1404/8/10');
//
//    if (preg_match('/\b(\d{4})\/(\d{1,2})\/(\d{1,2})\b/u', $converted, $matches)) {
//        try {
//            $tst = (new Jalalian((int) $matches[1], (int) $matches[2],(int) $matches[3]))->toCarbon()->setTimeFrom(\Carbon\Carbon::now());
//        } catch (\Exception $e) {
//            $tst = 'null';
//        }
//    }
//    dd($tst);
    $obj = json_decode('{"update_id":140,"message":{"message_id":244,"from":{"id":1497344206,"is_bot":false,"first_name":"\u062d\u0642 \u0634\u0646\u0627\u0633","last_name":null,"username":"mhdev"},"date":1761725628,"chat":{"id":1497344206,"type":"private","username":"mhdev","first_name":"\u062d\u0642 \u0634\u0646\u0627\u0633"},"media_group_id":"7792763388837159697","document":{"file_id":"1497344206:-2397828201417269501:1:360a867eae16ee4c91d1a34367c5d8c62fbca569238a9bc8","file_unique_id":null,"file_name":"IMG-20251027-WA0002.jpg","mime_type":"image\/jpeg","file_size":94633},"photo":[{"file_id":"1497344206:-2397828201417269501:1:360a867eae16ee4c91d1a34367c5d8c62fbca569238a9bc8","file_unique_id":null,"width":841,"height":1080,"file_size":94633}],"caption":"#\u0635\u0648\u0631\u062a\u062c\u0644\u0633\u0647 \u0645\u0648\u0631\u062e \u06f1\u06f4\u06f0\u06f4\/\u06f5\/\u06f9 \u0628\u0627 \u0645\u062f\u06cc\u0631 \u06a9\u0644 \u062a\u0627\u0645\u06cc\u0646 \u0627\u062c\u062a\u0645\u0627\u0639\u06cc \u0627\u0633\u062a\u0627\u0646 \u062f\u0631 \u0645\u062d\u0644 \u0628\u06cc\u0645\u0627\u0631\u0633\u062a\u0627\u0646 \u063a\u0631\u0636\u06cc \u0627\u0635\u0641\u0647\u0627\u0646\n\n- \u0645\u0639\u0631\u0641\u06cc \u06cc\u06a9 \u06af\u0631\u0648\u0647 \u06f5 \u0646\u0641\u0631\u0647 \u062e\u06cc\u0631 \u0628\u0631\u0627\u06cc \u0647\u0631 \u062f\u0631\u0645\u0627\u0646\u06af\u0627\u0647 \u062d\u0648\u0632\u0647 \u0627\u0646\u062a\u062e\u0627\u0628\u06cc\u0647 \u062a\u0627 \u06cc\u06a9 \u0647\u0641\u062a\u0647 \u0622\u06cc\u0646\u062f\u0647 @\u062e\u06cc\u0631\u06cc\n-\u0627\u0633\u062a\u0642\u0631\u0627\u0631 \u062f\u0646\u062f\u0627\u0646 \u067e\u0632\u0634\u06a9 \u062f\u0631 \u062f\u0631\u0645\u0627\u0646\u06af\u0627\u0647 \u062a\u0627\u0645\u06cc\u0646 \u0627\u062c\u062a\u0645\u0627\u0639\u06cc \u062f\u0648\u0644\u062a \u0622\u0628\u0627\u062f \u06f2 \u06cc\u0627 \u06f3 \u0631\u0648\u0632 \u062f\u0631 \u0647\u0641\u062a\u0647 \u062a\u0627 \u06cc\u06a9 \u0645\u0627\u0647 \u0622\u06cc\u0646\u062f\u0647 \n\n@\u0645\u062f\u06cc\u0631_\u06a9\u0644_\u062a\u0627\u0645\u06cc\u0646_\u0627\u062c\u062a\u0645\u0627\u0639\u06cc @\u062f\u0631\u0645\u0627\u0646\u06af\u0627\u0647_\u062a\u0627\u0645\u06cc\u0646_\u0627\u062c\u062a\u0646\u0627\u0639\u06cc_\u062f\u0648\u0644\u062a_\u0622\u0628\u0627\u062f"}}');

    $mp = new \App\Http\Controllers\ai\MinutesParser();
//    dd($obj->message);
//    $dp = $mp->parse($obj->message->caption);
//    dd($dp);
//    dd(\Morilog\Jalali\Jalalian::fromFormat('Y-m-d','1397-05-02')->toString());
    $user = auth()->user();
    try {
        echo $obj->message->caption2;
    }catch (Exception $e) {
       echo "کار بر" . ($user->name ?? 'بدون نام') . "\n\n" .$e->getMessage() . "\n کد " . $e->getCode() . "\n فایل " . $e->getFile() . "\n  خط" . $e->getLine();
    }
});

