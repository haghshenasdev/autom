<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Minutes;
use App\Models\Letter;
use App\Models\Task;
use App\Models\BaleUser;

class BaleBotController extends Controller
{
    public function webhook(Request $request)
    {
        try {

            $data = $request->input();
            $chatId = $data['message']['chat']['id'];
            $userMessage = $data['message']['from'];
            $text = $data['message']['text'] ?? '';
            $files = $data['message']['photo'] ?? [];

            // احراز هویت کاربر
            $bale_user = BaleUser::query()->where('bale_id', $userMessage['id'])->first();
            if ($bale_user == null) {
                $bale_user_auth = BaleUser::query()->where('bale_username', $text)->first();
                if ($bale_user_auth != null) {
                    $bale_user_auth->update([
                        'state' => 1,
                        'bale_username' => $userMessage['username'],
                        'bale_id' => $userMessage['id'],
                    ]);
                    $this->sendMessage($chatId, "✅ شما با موفقیت احراز هویت شدید !",[
                        [['📄 صورتجلسه'], ['📬 نامه']],
                        [['📝 کار']],
                    ]);
                    return response('احراز شده');
                }
                $this->sendMessage($chatId, "❌ شما احراز هویت نشده اید . \n  کد را از سامانه دریافت کن و برای من بفرست .");
                return response('احراز نشده');
            }

            // تشخیص هشتگ‌ها
            $hashtags = ['#صورتجلسه', '#صورت', '#صورت-جلسه', '#نامه', '#کار'];
            $matched = collect($hashtags)->filter(fn($tag) => str_contains($text, $tag))->first();



            // ذخیره در مدل مناسب
            $record = null;
        if (in_array($matched, ['#صورتجلسه', '#صورت', '#صورت-جلسه'])) {
            // استخراج عنوان
            $lines = explode("\n", $text);
            $title = null;
            foreach ($lines as $i => $line) {
                if (str_contains($line, $matched) && isset($lines[$i + 1])) {
                    $title = trim($lines[$i + 1]);
                    break;
                }
            }

            if (!$title) {
                // ارسال پیام برای دریافت عنوان
                $this->sendMessage($chatId, 'لطفاً عنوان را وارد کنید.');
                return response('عنوان خواسته شد');
            }

            // ذخیره فایل‌ها
            $savedFiles = [];
            foreach ($files as $file) {
                $path = Storage::put('bale_uploads', $file);
                $savedFiles[] = $path;
            }

            $record = Minutes::create([
                'title' => $title,
            ]);
        } elseif ($matched === '#نامه') {
            $record = Letter::create([
                'title' => '',
            ]);
        } elseif ($matched === '#کار') {
            $record = Task::create([
                'title' => '',
            ]);
        }

            // ارسال پیام تأیید
            if ($record) {
                $this->sendMessage($chatId, "ثبت شد ✅ آیدی: {$record->id}");
            }
        } catch (\Exception $e) {
            $this->sendMessage(1497344206, $e->getMessage());
        }

        return response('ok',200);
    }

    private function sendMessage($chatId, $text, $buttons = null): void
    {
        $token = env('BALE_BOT_TOKEN');

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($buttons) {
            $payload['ReplyKeyboardMarkup'] = [
                'keyboard' => $buttons,
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        Http::post("https://tapi.bale.ai/bot{$token}/sendMessage", $payload);
    }
}
