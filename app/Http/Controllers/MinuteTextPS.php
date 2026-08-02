<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ai\CategoryPredictor;
use App\Models\Task;
use App\Services\TempFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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
                'data' => json_decode($data),
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

    private function aiProcesses(string $text)
    {

        // ارسال به GapGPT برای اصلاح و تبدیل به ساختار مصوبات
        $aiResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('GAPGPT_API_KEY'),
        ])->post('https://api.gapgpt.app/v1/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => <<<EOT
متن زیر با OCR از یک صورتجلسه استخراج شده است. لطفاً آن را اصلاح کن و  بدون هیچ توضیح اظافه ای در قالب جیسون زیر بازگردان:
{
  'title' : "عنوان با توجه متن",
  'text' : "متن کامل اصلاح شده",
}
متن صورتجلسه :
{$text}
EOT
                ],
            ],
        ]);

return $aiResponse->json('choices.0.message.content');
    }
}
