<?php

namespace App\Http\Controllers;

use App\Services\TempFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class MinuteTextPS extends Controller
{

    /**
     * دریافت فایل و تبدیل به متن OCR
     */
    public function upload(Request $request)
    {

        if (!$request->hasFile('file')) {

            return response()->json([
                'success' => false,
                'message' => 'فایلی ارسال نشده است'
            ],422);

        }


        try {

            $file = $request->file('file');


            $content = file_get_contents(
                $file->getRealPath()
            );


            $extension = $file->getClientOriginalExtension();


            $tfs = new TempFileService();


            $filename = $tfs->save(
                $content,
                $extension
            );


            $ocr = $this->convert_to_text(
                url('/temp-download/'.$filename)
            );


            if (!$ocr[0]) {

                return response()->json([
                    'success'=>false,
                    'message'=>$ocr[1],
                    'file'=>$filename
                ],422);

            }


            return response()->json([

                'success'=>true,

                'data'=>[
                    'filename'=>$filename,
                    'text'=>$ocr[1]
                ]

            ]);


        }catch(Exception $e){

            Log::error($e->getMessage());

            return response()->json([
                'success'=>false,
                'message'=>$e->getMessage()
            ],500);

        }

    }



    /**
     * پردازش متن توسط AI
     */
    public function processText(Request $request)
    {

        $request->validate([
            'text'=>'required|string'
        ]);



        $result = $this->aiProcesses(
            $request->text
        );


        if(!$result){

            return response()->json([
                'success'=>false,
                'message'=>'خطا در پردازش هوش مصنوعی'
            ],500);

        }


        return response()->json([

            'success'=>true,

            'data'=>$result

        ]);

    }



    private function convert_to_text(string $url): array
    {

        try {


            $ocrResponse = Http::timeout(60)
                ->asForm()
                ->post(
                    'https://www.eboo.ir/api/ocr/getway',
                    [
                        'token'=>env('EBOO_OCR_TOKEN'),
                        'command'=>'addfile',
                        'filelink'=>$url,
                    ]
                );


            $ocrdata = json_decode(
                $ocrResponse->body()
            );


            if(!isset($ocrdata->FileToken)){

                return [
                    false,
                    'فایل توکن ایجاد نشد'
                ];

            }



            $ocrResponse2 = Http::timeout(60)
                ->asForm()
                ->post(
                    'https://www.eboo.ir/api/ocr/getway',
                    [
                        'token'=>env('EBOO_OCR_TOKEN'),
                        'command'=>'convert',
                        'output'=>'txtraw',
                        'filetoken'=>$ocrdata->FileToken,
                        'method'=>4,
                    ]
                );


            return [
                true,
                $ocrResponse2->body()
            ];


        }catch(Exception $e){

            return [
                false,
                $e->getMessage()
            ];

        }

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
                        شما یک پردازشگر حرفه ای OCR هستید.
                        وظیفه شما اصلاح متن استخراج شده از صورتجلسه است.
                        خروجی باید فقط JSON معتبر باشد.
                        هیچ توضیح، Markdown یا متن اضافی قبل یا بعد JSON ننویس.
                        نام ارگان‌ها، سازمان‌ها، شرکت‌ها و نهادهای ذکر شده در صورتجلسه را استخراج کن.
                        '
                        ],

                        [
                            'role' => 'user',
                            'content' => <<<TEXT

متن OCR شده صورتجلسه:

------------------
{$text}
------------------

خروجی فقط با ساختار زیر باشد:

{
    "title": "عنوان مناسب استخراج شده از متن",
    "text": "متن کامل اصلاح شده صورتجلسه"
}

TEXT
                        ]

                    ],

                    // اگر API سازگار باشد:
                    'response_format' => [
                        'type' => 'json_object'
                    ]

                ]);



            if(!$response->successful()){

                Log::error(
                    $response->body()
                );

                return null;

            }


            return json_decode(
                $response->json('choices.0.message.content'),
                true
            );


        }catch(Exception $e){

            Log::error($e->getMessage());

            return null;

        }

    }

}
