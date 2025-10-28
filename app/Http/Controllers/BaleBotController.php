<?php

namespace App\Http\Controllers;

use http\Client\Curl\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Minutes;
use App\Models\Letter;
use App\Models\Task;
use App\Models\BaleUser;
use Morilog\Jalali\Jalalian;

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
                    $this->sendMessage($chatId, "✅ شما با موفقیت احراز هویت شدید !");
                    return response('احراز شده');
                }
                $this->sendMessage($chatId, "❌ شما احراز هویت نشده اید . \n  کد را از سامانه دریافت کن و برای من بفرست .");
                return response('احراز نشده');
            }
            $this->sendMessage($chatId,'سلام');
            $user = \App\Models\User::query()->find($bale_user->user_id);
            switch ($text) {
                case '/صورتجلسه':
                    if (!$user->can('view_minutes')) {
                        $this->sendMessage($chatId, 'شما به صورت جلسه ها دسترسی ندارید !');
                        return response(' عدم دسترسی');
                    }
                    $minutes = null;
                    if (!$user->can('restore_any_minutes'))
                    {
                        $minutes = Minutes::query()->where('typer_id', $user->id)->latest()->limit(5)->get();
                    }
                    else
                    {
                        $minutes = Minutes::query()->latest()->limit(5)->get();
                    }
                    if ($minutes->isEmpty()) {
                        $this->sendMessage($chatId, '📭 هیچ صورتجلسه‌ای برای شما ثبت نشده است.');
                    } else {
                        $message = "🗂 لیست آخرین صورتجلسه‌های شما:\n\n";

                        foreach ($minutes as $minute) {
                            $message .= "📝 عنوان: {$minute->title}\n";
                            $message .= "🆔 آیدی: {$minute->id}\n";
                            $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($minute->created_at->format('Y-m-d')) . "\n";
                            $message .= "----------------------\n";
                        }

                        $this->sendMessage($chatId, $message);
                    }
                    return response(' صورتجلسه');
                case '/نامه':
                    $this->sendMessage($chatId, 'لطفاً متن نامه را ارسال کنید.');
                    return response(' نامه');
                case '/کار':
                    $this->sendMessage($chatId, 'لطفاً عنوان کار را وارد کنید.');
                    return response(' کار');
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

    private function sendMessage($chatId, $text): void
    {
        $token = env('BALE_BOT_TOKEN');

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        Http::post("https://tapi.bale.ai/bot{$token}/sendMessage", $payload);
    }
}
