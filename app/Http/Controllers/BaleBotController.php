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
    public function update(Request $request)
    {
        $token = env('BALE_BOT_TOKEN');
        $res = Http::get("https://tapi.bale.ai/bot{$token}/getUpdates", [
            'offset' => -1,
        ]);

        if ($res->successful()) {
            $updates = $res->json(); // تبدیل پاسخ به آرایه

            // بررسی اینکه آیا نتیجه‌ای در پاسخ وجود دارد
            if (isset($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    // فرض بر این است که هر آپدیت دارای یک پیام است
                    if (isset($update['message'])) {
                        // تبدیل آرایه به یک شیء Request برای متد webhook
                        $request = new Request($update);
                        $this->webhook($request);
                    }
                }
            }
        }
    }
    public function webhook(Request $request)
    {
        $this->sendMessage(1497344206, "سلام");
        try {

            $data = $request->all();
            $chatId = $data['message']['chat']['id'];
            $userMessage = $data['message']['from'];
            $text = $data['message']['text'] ?? '';
            $files = $data['message']['photo'] ?? [];


            // احراز هویت کاربر
            $bale_user = BaleUser::query()->where('bale_id', $userMessage['id'])->getModel();
            if ($bale_user == null and $bale_user->state == null) {
                $bale_user_auth = BaleUser::query()->where('bale_username', $text)->first();
                if ($bale_user_auth != null) {
                    $bale_user_auth->update([
                        'state' => 1,
                        'bale_username' => $userMessage['username'],
                        'bale_id' => $userMessage['id'],
                    ]);
                    $this->sendMessage($chatId, "شما با موفقیت احراز هویت شدید !");
                    return null;
                }
                $this->sendMessage($chatId, "شما احراز هویت نشده اید . \n  کد را از سامانه دریافت و برای من بفرستید .");
            }

            // تشخیص هشتگ‌ها
            $hashtags = ['#صورتجلسه', '#صورت', '#صورت-جلسه', '#نامه', '#کار'];
            $matched = collect($hashtags)->filter(fn($tag) => str_contains($text, $tag))->first();

            if (!$matched) return response('هشتگ معتبر یافت نشد');

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

            // ذخیره در مدل مناسب
            $record = null;
        if (in_array($matched, ['#صورتجلسه', '#صورت', '#صورت-جلسه'])) {
            $record = Minutes::create([
                'title' => $title,
            ]);
        } elseif ($matched === '#نامه') {
            $record = Letter::create([
                'title' => $title,
            ]);
        } elseif ($matched === '#کار') {
            $record = Task::create([
                'title' => $title,
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

    private function sendMessage($chatId, $text): void
    {
        $token = env('BALE_BOT_TOKEN');
        Http::post("https://tapi.bale.ai/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}
