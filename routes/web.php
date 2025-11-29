<?php

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

    Route::get('/private-show2/{path}', function ($path) {
        if (!Storage::disk('private2')->exists($path)) {
            abort(404);
        }

        $content = Storage::disk('private2')->get($path);

        return response($content, 200)
            ->header('Content-Type', getMimeTypeFromExtension($path))
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
    })->where('path', '.*');

    Route::get('/profiles/{path}', function ($path) {
        if (!Storage::disk('profile-photos')->exists($path)) {
            abort(404);
        }

        $content = Storage::disk('profile-photos')->get($path);

        return response($content, 200)
            ->header('Content-Type', getMimeTypeFromExtension($path))
            ->header('Content-Disposition', 'inline; filename="' . basename($path) . '"');
    })->where('path', '.*');

    Route::get('/private-dl2/{path}', function ($path) {
        if (!Storage::disk('private2')->exists($path)) {
            abort(404);
        }

        $content = Storage::disk('private2')->get($path);

        return response($content, 200)
            ->header('Content-Type', getMimeTypeFromExtension($path));
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
Route::get('/uaherituayhsrtuiaury/eeita',[\App\Http\Controllers\ReadChanel::class,'read']);


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
//    $obj = json_decode('{"update_id":380,"message":{"message_id":613,"from":{"id":1497344206,"is_bot":false,"first_name":"\u062d\u0642 \u0634\u0646\u0627\u0633","last_name":null,"username":"mhdev"},"date":1762358409,"chat":{"id":1497344206,"type":"private","username":"mhdev","first_name":"\u062d\u0642 \u0634\u0646\u0627\u0633"},"document":{"file_id":"1497344206:-6990514021637677311:1:ce9db56cfaf1c6941c99bf83eecaa948ec14eb958cd2849d","file_unique_id":null,"file_name":"IMG_20251105_191434.jpg","mime_type":"image\/jpeg","file_size":110088},"photo":[{"file_id":"1497344206:-6990514021637677311:1:ce9db56cfaf1c6941c99bf83eecaa948ec14eb958cd2849d","file_unique_id":null,"width":1061,"height":1500,"file_size":110088}],"caption":"#\u0646\u0627\u0645\u0647 \u06f4\u06f2\u06f0\u06f3\u06f6\u06f0\u06f6 \u0628\u0647 \u0631\u0626\u06cc\u0633 \u0633\u0627\u0632\u0645\u0627\u0646 \u0628\u0631\u0646\u0627\u0645\u0647 \u0648 \u0628\u0648\u062f\u062c\u0647 \u06a9\u0634\u0648\u0631 \u062c\u0647\u062a \u0627\u062e\u062a\u0635\u0627\u0635 \u062f\u0648 \u0645\u06cc\u0644\u06cc\u0627\u0631\u062f \u062a\u0648\u0645\u0627\u0646 \u0627\u0632 \u0627\u0639\u062a\u0628\u0627\u0631 \u062f\u0631 \u0627\u062e\u062a\u06cc\u0627\u0631 \u0646\u0645\u0627\u06cc\u0646\u062f\u0647 \u0628\u0647 \u062a\u062c\u0647\u06cc\u0632\u0627\u062a \u062f\u0627\u0646\u0634\u06af\u0627\u0647 \u06f1\u06f4\u06f0\u06f4\/\u06f8\/\u06f6\n\u067e\u06cc\u0631\u0648 \u06f6\u06f6\u06f5\u06f5\u06f3 @\u0646\u0638\u0631\u06cc\n=\u062f\u0627\u0646\u0634\u06af\u0627\u0647 \u067e\u06cc\u0627\u0645 \u0646\u0648\u0631 \u0634\u0627\u0647\u06cc\u0646 \u0634\u0647\u0631"}}');

//    $mp = new \App\Http\Controllers\ai\MinutesParser();
//    dd($obj->message);
//    $dp = $mp->parse($obj->message->caption);
//    dd($dp);
//    dd(\Morilog\Jalali\Jalalian::fromFormat('Y-m-d','1397-05-02')->toString());
//    $user = auth()->user();
//    try {
//        echo $obj->message->caption2;
//    }catch (Exception $e) {
//       echo "کار بر" . ($user->name ?? 'بدون نام') . "\n\n" .$e->getMessage() . "\n کد " . $e->getCode() . "\n فایل " . $e->getFile() . "\n  خط" . $e->getLine();
//    }

//    $ltp = new \App\Http\Controllers\ai\LetterParser();
//    $data = $ltp->parse("
//#نامه به رئیس سازمان برنامه و بودجه کشور جهت اختصاص دو میلیارد تومان از اعتبار در اختیار نماینده به تجهیزات دانشگاه ۱۴۰۴/۸/۶
//صادره دفتر تهران مکاتبه ۴۲۰۳۶۰۶ پیرومکاتبه 1-1 @نظری @طالبی
//=دانشگاه پیام نور شاهین شهر
//");
//    dd($data);
//
//    $text = 'پیرومکاتبه 1-1';
//    $piroNumber = null;
//    if (preg_match('/پیرومکاتبه\s+([^\n]+)/u', $text, $match)) {
//        $piroNumber = trim($match[1]);
//        if ($let = Letter::query()->where('mokatebe',$piroNumber)->first()){
//            $piroNumber = $let->id;
//        }else{
//            $piroNumber = null;
//        }
//    }
//    dd($piroNumber);

//    $user_id = auth()->id();
//    $re =\App\Models\Letter::query()->orWhere('user_id', $user_id)
//    ->orWhereHas('referrals', function ($query) use ($user_id) {
//        $query->where('to_user_id', $user_id); // نامه‌هایی که Referral.to_user_id برابر با آیدی کاربر لاگین شده است
//    });
//    dd($re->get());


//    dd('[بازکردن در سامانه]('.LetterResource::getUrl('edit',[1]).')' . "\n\n");
});

