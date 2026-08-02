<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ai\CategoryPredictor;
use App\Models\Task;
use App\Services\TempFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;
use Exception;

class MinuteTextPS extends Controller
{
    public function upload(Request $request)
    {

//        if (!$request->hasFile('file')) {
//            return response()->json([
//                'message' => 'فایلی ارسال نشده است'
//            ], 422);
//        }
//
//
//        $file = $request->file('file');
//
//
//        // محتوای فایل
//        $content = file_get_contents($file->getRealPath());
//
//
//        // پسوند فایل
//        $extension = $file->getClientOriginalExtension();
//
//
//        $tfs = new TempFileService();
//        $filename = $tfs->save(
//            $content,
//            $extension
//        );
//        $text = $this->convert_to_text(url('/temp-download/' . $filename));
        $text = $this->convert_to_text(url('/temp-download/'));
        if ($text[0]){
            $data = $this->aiProcesses($text[1]);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }else{
            return response()->json([
                'success' => false,
                'message' => "تبدیل فایل به متن انجام نشد" . $text[1],
//                'temp_url' => url('/temp-download/' . $filename),
            ],401);
        }
    }

    private function convert_to_text(string $url): array
    {
//        try {
//            $ocrResponse = Http::asForm()->post('https://www.eboo.ir/api/ocr/getway', [
//                'token' => env('EBOO_OCR_TOKEN'),
//                'command' => 'addfile',
//                'filelink' => $url,
//            ]);
//            $ocrdata = json_decode($ocrResponse->body());
//
//            if (!isset($ocrdata->FileToken)) {
//                return [false,'فایل توکن ایجاد نشد'];
//            } else {
//                $ocrResponse2 = Http::asForm()->post('https://www.eboo.ir/api/ocr/getway', [
//                    'token' => env('EBOO_OCR_TOKEN'),
//                    'command' => 'convert',
//                    'output' => 'txtraw',
//                    'filetoken' => $ocrdata->FileToken,
//                    'method' => 4,
//                ]);
//                $ocrText = $ocrResponse2->body();
//                return [true,$ocrText];
//            }
//        }catch (Exception $e){
//            return [false,$e->getMessage()];
//        }

        return [true,'نامه به میر فنرسکیان برای وام گرفتن برای فلانی'];
    }

    private function aiProcesses(string $text): ?array
    {
        try {

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . env('GAPGPT_API_KEY'),
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.gapgpt.app/v1/chat/completions', [

                    'model' => 'gpt-4o',

                    'temperature' => 0.1,

                    'messages' => [

                        [
                            'role' => 'system',
                            'content' => '
                         شما یک متخصص پردازش اسناد اداری و صورتجلسه هستید.

                        وظایف:
                        1- متن OCR شده را اصلاح کن.
                        2- یک عنوان رسمی و کوتاه برای بایگانی ایجاد کن.
                        3- نام ارگان‌ها، سازمان‌ها، شرکت‌ها و نهادهای ذکر شده در صورتجلسه را استخراج کن.

                        قوانین:
                        - خروجی فقط JSON معتبر باشد.
                        - هیچ توضیح اضافی ننویس.
                        - اگر ارگانی پیدا نشد آرایه organizations خالی باشد.
                        - نام کامل رسمی ارگان‌ها را استخراج کن.
                        '
                        ],

                        [
                            'role' => 'user',
                            'content' => <<<TEXT

متن OCR شده صورتجلسه:

------------------
{$text}
------------------


خروجی دقیقاً با این ساختار باشد:

{
    "title": "عنوان رسمی و کوتاه صورتجلسه",
    "text": "متن اصلاح شده کامل",
    "organizations": [
        "نام ارگان یا سازمان"
    ]
}


TEXT
                        ]

                    ],

                    // اگر API سازگار باشد:
                    'response_format' => [
                        'type' => 'json_object'
                    ]

                ]);


            if (!$response->successful()) {

                Log::error('AI Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return null;
            }


            $content = $response->json('choices.0.message.content');


            if (!$content) {
                return null;
            }


            // تبدیل خروجی AI به آرایه PHP
            return json_decode($content, true);


        } catch (\Throwable $e) {

            Log::error('AI Exception', [
                'message' => $e->getMessage()
            ]);

            return null;
        }
    }
}
