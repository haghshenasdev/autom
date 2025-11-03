<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ai\CategoryPredictor;
use App\Models\City;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\Minutes;
use App\Models\Letter;
use App\Models\Task;
use App\Models\BaleUser;
use Illuminate\Support\Facades\Storage;
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
            $caption = $data['message']['caption'] ?? '';
            $date = $data['date'] ?? now()->toDateTime();
            $media_group_id = $data['message']['media_group_id'] ?? null;
//            $this->sendMessage($chatId, json_encode($data));


            // احراز هویت کاربر
            $bale_user = BaleUser::query()->where('bale_id', $userMessage['id'])->first();
            if ($bale_user == null) {
                $bale_user_auth = BaleUser::query()->where('bale_username', $text)->first();
                if ($bale_user_auth != null) {
                    $bale_user_auth->update([
                        'state' => '1',
                        'bale_username' => $userMessage['username'] ?? null,
                        'bale_id' => $userMessage['id'],
                    ]);
                    $this->sendMessage($chatId, "✅ شما با موفقیت احراز هویت شدید !");
                    return response('احراز شده');
                }
                $this->sendMessage($chatId, "❌ شما احراز هویت نشده اید . \n  کد را از سامانه دریافت کن و برای من بفرست .");
                return response('احراز نشده');
            }
            $user = \App\Models\User::query()->find($bale_user->user_id);

            if ($text != '')
            {
                switch ($text) {
                    case '/صورتجلسه':

                        if (!$user->can('view_minutes')) {
                            $this->sendMessage($chatId, '❌ شما به صورت‌جلسه‌ها دسترسی ندارید!');
                            return response('عدم دسترسی');
                        }

// گرفتن لیست صورت‌جلسه‌ها
                        $query = Minutes::query()->orderByDesc('id')->limit(5);

                        if (!$user->can('restore_any_minutes')) {
                            $query->where('typer_id', $user->id);
                        }

                        $minutes = $query->get();

// ارسال پیام
                        if ($minutes->isEmpty()) {
                            $this->sendMessage($chatId, '📭 هیچ صورت‌جلسه‌ای برای شما ثبت نشده است.');
                            return response('صورت‌جلسه خالی');
                        }

                        $message = "🗂 لیست آخرین صورت‌جلسه‌های شما:\n\n";

                        foreach ($minutes as $minute) {
                            $message .= "📝 عنوان: {$minute->title}\n";
                            $message .= "🆔 آیدی: {$minute->id}\n";
                            if ($minute->date) {
                                $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($minute->date)->format('Y/m/d') . "\n";
                            }
                            $message .= "----------------------\n";
                        }

                        $this->sendMessage($chatId, $message);
                        return response('صورت‌جلسه ارسال شد');
                    case '/نامه':
                        if (!$user->can('view_letter')) {
                            $this->sendMessage($chatId, '❌ شما به نامه ‌ها دسترسی ندارید!');
                            return response('عدم دسترسی');
                        }

                        $query = Letter::query()->orderByDesc('id')->limit(5);

                        if (!$user->can('restore_any_letter')) {
                            $query->where('user_id', $user->id);
                        }

                        $minutes = $query->get();

// ارسال پیام
                        if ($minutes->isEmpty()) {
                            $this->sendMessage($chatId, '📭 هیچ نامه‌ای برای شما ثبت نشده است.');
                            return response('نامه خالی');
                        }

                        $message = "🗂 لیست آخرین نامه های شما:\n\n";

                        foreach ($minutes as $minute) {
                            $message .= "📝 عنوان: {$minute->subject}\n";
                            $message .= "🆔 شماره ثبت: {$minute->id}\n";
                            if ($minute->created_at) {
                                $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($minute->created_at)->format('Y/m/d') . "\n";
                            }
                            $message .= "----------------------\n";
                        }

                        $this->sendMessage($chatId, $message);
                        return response('نامه ارسال شد');
                    case '/کار':
                        if (!$user->can('view_task')) {
                            $this->sendMessage($chatId, '❌ شما به کار ‌ها دسترسی ندارید!');
                            return response('عدم دسترسی');
                        }

                        $query = Task::query()->orderByDesc('id')->limit(5);

                        if (!$user->can('restore_any_task')) {
                            $query->where('Responsible_id', $user->id);
                        }

                        $minutes = $query->get();

// ارسال پیام
                        if ($minutes->isEmpty()) {
                            $this->sendMessage($chatId, '📭 هیچ کاری برای شما ثبت نشده است.');
                            return response('کار خالی');
                        }

                        $message = "🗂 لیست آخرین کار های شما:\n\n";

                        foreach ($minutes as $minute) {
                            $message .= "📝 عنوان: {$minute->name}\n";
                            $message .= "🆔 شماره ثبت: {$minute->id}\n";
                            if ($minute->created_at) {
                                $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($minute->created_at)->format('Y/m/d') . "\n";
                            }
                            $message .= "----------------------\n";
                        }

                        $this->sendMessage($chatId, $message);
                        return response('کار ارسال شد');
                }

                // بررسی شروع با #کار
                if (str_starts_with($text, '#کار')) {
                    // حذف #کار از ابتدای متن و تمیز کردن فاصله‌ها
                    $title = trim(substr($text, strlen('#کار')));

                    $catPreder = new CategoryPredictor();
                    $cats = $catPreder->predictWithCity($title);
                    $time = $catPreder->extractDateFromTitle($title) ?? Carbon::now();
                    if ($cats) {
                        $data = [
                            'name' => mb_substr($catPreder->cleanTitle($title), 0, 350),
                            'description' => $text,
                            'created_at' => $time,
                            'completed_at' => $time,
                            'started_at' => $time,
                            'completed' => 1,
                            'status' => 1,
                            'Responsible_id' => $user->id,
                            'city_id' => $cats['city'],
                        ];
                        $task = Task::create($data);
                        $task->project()->attach($cats['categories']);
                        $task->group()->attach([32,($user->id  == 20) ? 1 : 2]);

                        //پیام
                        $data['city_id'] = City::find($data['city_id'])->name ?? 'نامشخص';
                        $data['started_at'] = Jalalian::fromDateTime($data['started_at'])->format('Y/m/d');

                        $message = " 📌 *عنوان:* {$data['name']}\n";
                        $message .= " 🆔 *شماره ثبت:* {$task->id}\n";
                        $message .= " 🕒 *تاریخ:* {$data['started_at']}\n";
                        $message .= "✅ *وضعیت:* انجام شده\n";
                        $message .= "📍 *شهر:* {$data['city_id']}\n";
                        $message .= "👤 *مسئول:* {$user->name}";

                        $this->sendMessage($chatId,$message);
                    }

                    return response("Task ذخیره شد: " . $title);
                }

            }
            elseif ($caption != '')
            {
                // تشخیص هشتگ‌ها
                $hashtags = ['#صورتجلسه', '#صورت', '#صورت-جلسه', '#نامه', '#کار'];
                $matched = collect($hashtags)->filter(fn($tag) => str_contains($caption, $tag))->first();


                // ذخیره در مدل مناسب
                $record = null;
                if (in_array($matched, ['#صورتجلسه', '#صورت', '#صورت-جلسه'])) {
                    $mp = new \App\Http\Controllers\ai\MinutesParser();
                    $parsedData = $mp->parse($caption);

                    $mdata = [
                        'title' => $parsedData['title'],
                        'date' => $parsedData['title_date'] ?? $date,
                        'text' => $caption,
                        'typer_id' => $user->id,
                        'task_id' => $parsedData['task_id'],
                    ];
                    $this->sendMessage($chatId, "📝🔄 در حال پردازش و ذخیره سازی صورت جلسه با مشخصات زیر \n\nعنوان : {$mdata['title']}\nتاریخ : ".$mdata['date']."\nنويسنده : {$user->name}\nجلسه : {$mdata['task_id']}\n");
                    $record = Minutes::create($mdata);
                    $record->organ()->attach($parsedData['organs']);
                    $record->group()->attach(1);
                    foreach ($parsedData['approves'] as $approve) {
                        $cp = new \App\Http\Controllers\ai\CategoryPredictor();
                        $keywords = $cp->extractKeywords($approve['text']);
                        $task = Task::create([
                            'name' => $approve['text'],
                            'started_at' => $mdata['date'],
                            'created_at' => $mdata['date'],
                            'ended_at' => $approve['due_at'] ?? null,
                            'Responsible_id' => $approve['user']['id'] ?? $user->id,
                            'minutes_id' => $record->id,
                            'city_id' => $cp->detectCity($keywords),
                            'organ_id' => $cp->detectOrgan($keywords),
                        ]);
                        $task->group()->attach([33,32]); // دسته بندی هوش مصنوعی و مصوبه
                    }


                    if (isset($data['message']['document']))
                    {
                        $doc = $data['message']['document'];
                        $record->update(['file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION)]);
                        Storage::disk('private_appendix_other')->put($record->getFilePath(), $this->getFile($doc['file_id']));
                        if ($media_group_id){
                            $bale_user->update(['state' => $media_group_id . "_{$record->id}"]);
                        }
                    }

                } elseif ($matched === '#نامه') {
                    $record = Letter::create([
                        'subject' => '',
                    ]);
                }

                // ارسال پیام تأیید
                if ($record) {
                    $this->sendMessage($chatId, "ثبت شد ✅ آیدی: {$record->id}");
                }
                return response('ok', 200);
            }
            if ($media_group_id){
                $media_group_data = explode('_', $bale_user->sate);
                if ($media_group_id == $media_group_data[0]){
                    $record = Minutes::query()->find($media_group_data[1])->getModel();
                    $doc = $data['message']['document'];
                    $appendix_other = $record->appendix_others()->create(['file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION)]);
                    Storage::disk('private_appendix_other')->put($appendix_other->getFilePath(), $this->getFile($doc['file_id']));
                    $bale_user->update(['state' => '1']);
                }
            }
        } catch (Exception $e) {
            $userName = $user->name ?? ($userMessage['first_name'] ?? 'نامشخص');

            $message = "خطا ❌\n";
            $message .= " کاربر: {$userName}\n\n";
            $message .= " شرح: " . $e->getMessage() . "\n\n";
            $message .= "کد: " . $e->getCode() . "\n\n";
            $message .= "فایل: " . $e->getFile() . "\n\n";
            $message .= "خط: " . $e->getLine();

            $this->sendMessage(1497344206, $message);
        }

        return response('ok', 200);
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

    private function getFile($filePath)
    {
        $token = env('BALE_BOT_TOKEN');

        return file_get_contents("https://tapi.bale.ai/file/bot{$token}/{$filePath}");
    }
}
