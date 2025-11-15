<?php

namespace App\Http\Controllers;

use App\Filament\Resources\LetterResource;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\TaskResource;
use App\Http\Controllers\ai\CategoryPredictor;
use App\Http\Controllers\ai\LetterParser;
use App\Models\Cartable;
use App\Models\City;
use App\Models\Project;
use App\Models\Referral;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use App\Models\Minutes;
use App\Models\Letter;
use App\Models\Task;
use App\Models\BaleUser;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

class BaleBotController extends Controller
{
    public function webhook(Request $request)
    {
        try {
            $data = $request->input();
            // هندل کردن callback_query
            if (isset($data['callback_query'])) {
                $this->handleCallbackQuery($request);
                return response('callback handled');
            }
            $chatId = $data['message']['chat']['id'] ?? null;
            $userMessage = $data['message']['from'] ?? null;
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
                    $this->sendMessageWithReplyKeyboard($chatId, "✅ شما با موفقیت احراز هویت شدید !" . "\n" . "با ارسال دستور /راهنما می توانید لیست دستورات کار با ربات را دریافت نمایید .");
                    return response('احراز شده');
                }
                if (isset($data['message']['chat']['type']) and $data['message']['chat']['type'] == "private") $this->sendMessage($chatId, "❌ شما احراز هویت نشده اید . \n  کد را از سامانه دریافت کن و برای من بفرست .");
                return response('احراز نشده');
            }
            $user = \App\Models\User::query()->find($bale_user->user_id);


            if ($media_group_id) {
                $doc = $data['message']['document'];
                if($caption == ''){
                    $bale_user->update(['state' => $media_group_id . '_' . $doc['file_id'] . '_' . pathinfo($doc['file_name'], PATHINFO_EXTENSION)]);
                }

            }

            if ($text != '') {
                $text = trim(CalendarUtils::convertNumbers($text,true)); // حذف فاصله‌های اضافی و تبدیل اعداد فارسی
                $lines = explode("\n", $text);
                $firstLine = $lines[0] ?? '';
                $secondLine = $lines[1] ?? '';

                if (str_starts_with($firstLine, '/کارپوشه')) {
                    if (!$user->can('view_cartable')) {
                        $this->sendMessage($chatId, '❌ شما به کارپوشه دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $queryText = trim(str_replace('/کارپوشه', '', $firstLine));
                    $completionKeywords = ['#انجام', '#شد', '#انجام_شد' , '#بررسی'];
                    $isCompletion = collect($completionKeywords)->contains(function ($kw) use ($text) {
                        return mb_strpos($text, $kw) !== false;
                    });
                    if ($isCompletion) $queryText = trim(str_replace($completionKeywords, '', $queryText));
                    $completionKeywords = ['#همه',];
                    $isFilter = collect($completionKeywords)->contains(function ($kw) use ($text) {
                        return mb_strpos($text, $kw) !== false;
                    });
                    if ($isFilter) $queryText = trim(str_replace($completionKeywords, '', $queryText));

                    $query = Cartable::query()->where('user_id',$user->id);

                    if (is_numeric($queryText)) {
                        $query->WhereHas('letter', function ($query) use ($queryText) {
                            $query->where('id', $queryText);
                        });
                    } elseif ($queryText !== '') {
                        $query->WhereHas('letter', function ($query) use ($queryText) {
                            $query->where('subject', 'like', "%{$queryText}%");
                        });
                    } else {
                        $query->orderByDesc('id')->limit(5);
                    }

                    if (!$isFilter) {
                        $query->where('checked','!=',1);
                    }

                    $letters = $query->get();

                    if ($letters->isEmpty()) {
                        $this->sendMessage($chatId, '📭 هیچ نامه ای در کارپوشه مطابق با جستجوی شما یافت نشد.');
                        return response('پوشه خالی');
                    }

                    $message = $queryText ? "🔍 نتیجه جستجو برای «{$queryText}»:\n\n" : "🗂 لیست آخرین نامه های موچود در کارپوشه شما:\n\n";


                    foreach ($letters as $letter) {
                        if ($isCompletion and $letters->count() == 1) {
                            $letter->checked = 1;
                            $letter->save();
                        }
                        $message .= "📝 عنوان: {$letter->letter->subject}\n";
                        $message .= "🆔 شماره ثبت: {$letter->letter->id}\n";
                        $message .= "✔️ وضعیت بررسی : ". ($letter->checked == 1 ? "✅ بررسی شده" : "❌ بررسی نشده") ."\n";
                        if ($letter->letter->created_at) {
                            $message .= "📅 تاریخ ثبت نامه: " . Jalalian::fromDateTime($letter->letter->created_at)->format('Y/m/d') . "\n";
                        }
                        if ($letter->created_at) {
                            $message .= "📅 تاریخ ثبت در کارتابل: " . Jalalian::fromDateTime($letter->created_at)->format('Y/m/d') . "\n";
                        }
                        $message .= '[بازکردن در سامانه]('.LetterResource::getUrl('edit',[$letter->letter->id]).')' . "\n";
                        $message .= "----------------------\n";
                    }

                    $this->sendMessage($chatId, $message);
                    return response('کارپوشه ارسال شد');

                }elseif (str_starts_with($firstLine, '/ارجاع')) {
                    if (!$user->can('view_referral')) {
                        $this->sendMessage($chatId, '❌ شما به ارجاع ها دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $queryText = trim(str_replace('/ارجاع', '', $firstLine));
                    $completionKeywords = ['#انجام', '#شد', '#انجام_شد' , '#بررسی'];
                    $isCompletion = collect($completionKeywords)->contains(function ($kw) use ($text) {
                        return mb_strpos($text, $kw) !== false;
                    });
                    if ($isCompletion) $queryText = trim(str_replace($completionKeywords, '', $queryText));

                    $query = Referral::query()->where('to_user_id',$user->id);

                    if (is_numeric($queryText)) {
                        $query->WhereHas('letter', function ($query) use ($queryText) {
                            $query->where('id', $queryText);
                        });
                    } elseif ($queryText !== '') {
                        $query->WhereHas('letter', function ($query) use ($queryText) {
                            $query->where('subject', 'like', "%{$queryText}%");
                        });
                    } else {
                        $query->orderByDesc('id')->limit(5);
                    }

                    $letters = $query->get();

                    if ($letters->isEmpty()) {
                        $this->sendMessage($chatId, '📭 هیچ ارجاعی مطابق با جستجوی شما یافت نشد.');
                        return response('پوشه خالی');
                    }

                    $message = $queryText ? "🔍 نتیجه جستجو برای «{$queryText}»:\n\n" : "🗂 لیست آخرین ارجاع های شما:\n\n";


                    foreach ($letters as $letter) {
                        if ($isCompletion and $letters->count() == 1) {
                            $letter->checked = 1;
                            $letter->save();
                        }
                        $message .= "📝 عنوان: {$letter->letter->subject}\n";
                        $message .= "🆔 شماره ثبت: {$letter->letter->id}\n";
                        $message .= "✔️ وضعیت بررسی : ". ($letter->checked == 1 ? "✅ بررسی شده" : "❌ بررسی نشده") ."\n";
                        if ($letter->rule) $message .= "ℹ️ دستور : ". $letter->rule ."\n";
                        $message .= "↖️ توسط : ". $letter->by_users->name ."\n";
                        if ($letter->letter->created_at) {
                            $message .= "📅 تاریخ ثبت نامه: " . Jalalian::fromDateTime($letter->letter->created_at)->format('Y/m/d') . "\n";
                        }
                        if ($letter->created_at) {
                            $message .= "📅 تاریخ ثبت در کارتابل: " . Jalalian::fromDateTime($letter->created_at)->format('Y/m/d') . "\n";
                        }
                        $message .= '[بازکردن در سامانه]('.LetterResource::getUrl('edit',[$letter->letter->id]).')' . "\n";
                        $message .= "----------------------\n";
                    }

                    $this->sendMessage($chatId, $message);
                    return response('ارجاع ارسال شد');

                }
                elseif (str_starts_with($firstLine, '/کار')) {
                    if (!$user->can('view_task')) {
                        $this->sendMessage($chatId, '❌ شما به کارها دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $queryText = trim(str_replace('/کار', '', $firstLine));
                    $completionKeywords = ['#انجام', '#شد', '#انجام_شد'];
                    $isCompletion = collect($completionKeywords)->contains(function ($kw) use ($text) {
                        return mb_strpos($text, $kw) !== false;
                    });
                    if ($isCompletion) $queryText = trim(str_replace($completionKeywords, '', $queryText));

                    $query = Task::query();

                    if (is_numeric($queryText)) {
                        $query->where('id', $queryText);
                    } elseif ($queryText !== '') {
                        $query->where('name', 'like', "%{$queryText}%");
                    } else {
                        $query->orderByDesc('id')->limit(5);
                    }

                    if ($secondLine != ''){
                        $queryMinText = trim($secondLine);
                        if (is_numeric($queryText)) {
                            $query->where('minutes_id', $queryMinText);
                        } else {
                            $minute = Minutes::query()->where('title', 'like', "%{$queryMinText}%")->first();
                            if ($minute) $query->where('minutes_id', $minute->id);
                        }
                    }

                    if (!$user->can('restore_any_task')) {
                        $query->where('Responsible_id', $user->id);
                    }

                    $tasks = $query->get();

                    if ($tasks->isEmpty()) {
                        $this->sendMessage($chatId, '📭 هیچ کاری مطابق با جستجوی شما یافت نشد.');
                        return response('کار خالی');
                    }

                    $message = $queryText ? "🔍 نتیجه جستجو برای «{$queryText}»:\n\n" : "🗂 لیست آخرین کارهای شما:\n\n";

                    foreach ($tasks as $task) {
                        if ($isCompletion && !$task->completed) {
                            $task->completed = 1;
                            $task->completed_at = now();
                            $task->save();
                            $message .= "🔁 وضعیت کار «{$task->name}» به انجام‌شده تغییر یافت.\n\n";
                        }

                        $message .= "📝 عنوان: {$task->name}\n";
                        $message .= "🆔 شماره ثبت: {$task->id}\n";
                        $message .= "ℹ️ وضعیت انجام: " . ($task->completed ? '✅ انجام شده' : '❌ انجام نشده') ."\n";
                        if ($user->can('restore_any_task')) $message .= "👤 مسئول: {$task->responsible->name}\n";
                        $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($task->created_at)->format('Y/m/d') . "\n";
                        if ($task->completed and $task->completed_at) $message .= "📅 تاریخ انجام: " . Jalalian::fromDateTime($task->completed_at)->format('Y/m/d') . "\n";
                        $message .= "\n" . '[بازکردن در سامانه]('.TaskResource::getUrl('edit',[$task->id]).')' . "\n\n";
                        $message .= "----------------------\n";
                    }

                    $this->sendMessage($chatId, $message);
                    return response('کار ارسال شد');

                } elseif (str_starts_with($firstLine, '/صورتجلسه')) {
                    if (!$user->can('view_minutes')) {
                        $this->sendMessage($chatId, '❌ شما به صورت‌جلسه‌ها دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $queryText = trim(str_replace('/صورتجلسه', '', $firstLine));
                    $query = Minutes::query();

                    if (is_numeric($queryText)) {
                        $query->where('id', $queryText);
                    } elseif ($queryText !== '') {
                        $query->where('title', 'like', "%{$queryText}%");
                    } else {
                        $query->orderByDesc('id')->limit(5);
                    }

                    if (!$user->can('restore_any_minutes')) {
                        $query->where('typer_id', $user->id);
                    }

                    $minutes = $query->get();

                    if ($minutes->isEmpty()) {
                        $this->sendMessage($chatId, '📭 هیچ صورت‌جلسه‌ای مطابق با جستجوی شما یافت نشد.');
                        return response('صورت‌جلسه خالی');
                    }

                    $message = $queryText ? "🔍 نتیجه جستجو برای «{$queryText}»:\n\n" : "🗂 لیست آخرین صورت‌جلسه‌های شما:\n\n";
                    foreach ($minutes as $minute) {
                        $message .= $this->createMinuteMessage($minute,$user,$queryText !== '');
                        $message .= "----------------------\n";
                    }

                    $this->sendMessage($chatId, $message);
                    return response('صورت‌جلسه ارسال شد');

                } elseif (str_starts_with($firstLine, '/نامه')) {
                    if (!$user->can('view_letter')) {
                        $this->sendMessage($chatId, '❌ شما به نامه‌ها دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $queryText = trim(str_replace('/نامه', '', $firstLine));
                    $query = Letter::query();

                    if (is_numeric($queryText)) {
                        $query->where('id', $queryText);
                    } elseif ($queryText !== '') {
                        $queryTextPersent = str_replace(' ', '%', $queryText);
                        $query->where('subject', 'like', "%{$queryTextPersent}%")->limit(5);
                    } else {
                        $query->orderByDesc('id')->limit(5);
                    }

                    if (!$user->can('restore_any_letter')) {
                        $query->orWhere('user_id', $user->id)->orWhereHas('referrals', function ($quer) use ($user) {
                            $quer->where('to_user_id', $user->id); // نامه‌هایی که Referral.to_user_id برابر با آیدی کاربر لاگین شده است
                        })->orWhereHas('users', function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        });
                    }

                    $page = 1;
                    $perPage = 5;
                    $letters = $query->forPage($page, $perPage)->get();
                    $totalPages = ceil($query->count() / $perPage);

                    if ($letters->isEmpty()) {
                        $this->sendMessage($chatId, '📭 هیچ نامه‌ای مطابق با جستجوی شما یافت نشد.');
                        return response('نامه خالی');
                    }

                    if (count($letters) == 1){
                        $message = '[بازکردن در سامانه]('.LetterResource::getUrl('edit',[$letters[0]->id]).')' . "\n\n";
                        $message .= $this->CreateLetterMessage($letters[0]);
                        $path = $letters[0]->getFilePath();
                        $this->sendDocumentFromContent($chatId,Storage::disk('private')->get($path),basename($path),$this->getMimeTypeFromExtension($path),$message);
                    }else{
                        $message = $queryText ? "🔍 نتیجه جستجو برای «{$queryText}» - صفحه {$page}:\n\n" : "🗂 لیست نامه‌های شما - صفحه {$page} از {$query->count()}:\n\n";

                        foreach ($letters as $letter) {
                            $message .= "📝 عنوان: {$letter->subject}\n";
                            $message .= "🆔 شماره ثبت: {$letter->id}\n";
                            if ($letter->created_at) {
                                $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($letter->created_at)->format('Y/m/d') . "\n";
                            }
                            $message .= '[بازکردن در سامانه]('.LetterResource::getUrl('edit',[$letter->id]).')' . "\n";
                            $message .= "----------------------\n";
                        }

                        $keyboard = ['inline_keyboard' => []];
                        $buttons = [];

                        if ($page > 1) {
                            $buttons[] = ['text' => '⬅️ قبلی', 'callback_data' => "letter_page_" . ($page - 1)];
                        }
                        if ($page < $totalPages) {
                            $buttons[] = ['text' => '➡️ بعدی', 'callback_data' => "letter_page_" . ($page + 1)];
                        }
                        if (!empty($buttons)) {
                            $keyboard['inline_keyboard'][] = $buttons;
                        }
                        $this->sendMessageWithKeyboard($chatId, $message, $keyboard);
                    }

                    return response('نامه ارسال شد');
                } elseif (str_starts_with($text, '#کار')) {
                    // حذف #کار از ابتدای متن و تمیز کردن فاصله‌ها
                    $title = trim(substr($text, strlen('#کار')));

                    $catPreder = new CategoryPredictor();
                    $cats = $catPreder->predictWithCityOrgan($title);
                    $time = $catPreder->extractDateFromTitle($title) ?? Carbon::now();
                    if ($cats) {
                        $dataTask = [
                            'name' => mb_substr($catPreder->cleanTitle($title), 0, 350),
                            'description' => $text,
                            'created_at' => $time,
                            'completed_at' => $time,
                            'started_at' => $time,
                            'completed' => 1,
                            'status' => 1,
                            'Responsible_id' => $user->id,
                            'city_id' => $cats['city'],
                            'organ_id' => $cats['organ'],
                        ];
                        $task = Task::create($dataTask);
                        $task->project()->attach($cats['categories']);
                        $task->group()->attach([32, ($user->id == 20) ? 1 : 2]);

                        //پیام
                        $dataTask['city_id'] = City::find($dataTask['city_id'])->name ?? 'نامشخص';
                        $dataTask['started_at'] = Jalalian::fromDateTime($dataTask['started_at'])->format('Y/m/d');

                        $message = " 📌 *عنوان:* {$dataTask['name']}\n";
                        $message .= " 🆔 *شماره ثبت:* {$task->id}\n";
                        $message .= " 🕒 *تاریخ:* {$dataTask['started_at']}\n";
                        $message .= "✅ *وضعیت:* انجام شده\n";
                        $message .= "📍 *شهر:* {$dataTask['city_id']}\n";
                        $message .= "👤 *مسئول:* {$user->name}";
                        $message .= "\n" . '[بازکردن در سامانه]('.TaskResource::getUrl('edit',[$task->id]).')' . "\n\n";

                        $this->sendMessage($chatId, $message);
                    }

                    return response("Task ذخیره شد: " . $title);
                } elseif (str_starts_with($firstLine, '/راهنما')) {
                    $queryText = trim(str_replace('/راهنما', '', $firstLine));
                    $message = $this->HelpHandler($queryText);

                    $this->sendMessage($chatId, $message);
                    return response("راهنما ارسال شد .");
                } elseif (str_starts_with($firstLine, '/آمار')){
                    $message = "📈 آمار \n\n";
                    $message .= "📄 نامه های شما : " . Letter::query()->whereHas('users', function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        }) // نامه‌هایی که user_id برابر با آیدی کاربر لاگین شده است
                        ->orWhereHas('referrals', function ($query) use ($user) {
                            $query->where('to_user_id', $user->id); // نامه‌هایی که Referral.to_user_id برابر با آیدی کاربر لاگین شده است
                        })->count() ."\n";
                    $message .= "↖️ ارجاع بررسی نشده : " . Referral::query()->where('to_user_id',$user->id)->whereNot('checked',1)->count() ."\n";
                    $message .= "🧰  کار پوشه بررسی نشده : " . Cartable::query()->where('user_id',$user->id)->whereNot('checked',1)->count()."\n";
                    $message .= "ℹ️ پروژه های شما : " . Project::query()->where('user_id',$user->id)->count() ."\n";
                    $message .= "🕹️ کار های شما : " . Task::query()->where('Responsible_id',$user->id)->count() ."\n";
                    $message .= "📝 صورت جلسه های شما : " . Minutes::query()->where('typer_id',$user->id)->count();

                    $this->sendMessage($chatId, $message);
                    return response("آمار ارسال شد .");
                }

            } elseif ($caption != '') {
                $caption = CalendarUtils::convertNumbers($caption,true); // تبدیل اعداد فارسی به انگلیسی
                // تشخیص هشتگ‌ها
                $hashtags = ['#صورتجلسه', '#صورت', '#صورت-جلسه', '#نامه', '#کار'];
                $matched = collect($hashtags)->filter(fn($tag) => str_contains($caption, $tag))->first();


                // ذخیره در مدل مناسب
                $record = null;
                if (in_array($matched, ['#صورتجلسه', '#صورت', '#صورت-جلسه'])) {
                    if (!$user->can('create_minutes')) {
                        $this->sendMessage($chatId, '❌ شما برای ایجاد صورت‌جلسه‌ دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }
                    $mp = new \App\Http\Controllers\ai\MinutesParser();
                    $parsedData = $mp->parse($caption);

                    $mdata = [
                        'title' => $parsedData['title'],
                        'date' => $parsedData['title_date'] ?? $date,
                        'text' => $caption,
                        'typer_id' => $user->id,
                        'task_id' => $parsedData['task_id'],
                    ];
                    $this->sendMessage($chatId, "📝🔄 در حال پردازش و ذخیره سازی صورت جلسه" . "\n");

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
                        $task->group()->attach([33, 32]); // دسته بندی هوش مصنوعی و مصوبه
                    }

                    $message = '✅ صورت جلسه با مشخصات زیر ذخیره شد : ' . "\n\n";
                    $message .= $this->createMinuteMessage($record,$user);
                    $this->sendMessage($chatId,$message);

                    if (isset($data['message']['document'])) {
                        $doc = $data['message']['document'];
                        $record->update(['file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION)]);
                        Storage::disk('private_appendix_other')->put($record->getFilePath(), $this->getFile($doc['file_id']));
                        if ($media_group_id) {
                            $state_data = explode('_', $bale_user->state);
                            $this->sendMessage(1497344206,json_encode($state_data));
                            if ($state_data[0] == "$media_group_id"){
                                $appendix_other = $record->appendix_others()->create(['file' => $state_data[2]]);
                                Storage::disk('private_appendix_other')->put($appendix_other->getFilePath(), $this->getFile($state_data[1]));
                                return response('فایل ضمیه شد');
                            }
                        }
                        $bale_user->update(['state' => '1']);
                    }

                } elseif ($matched === '#نامه') {
                    if (!$user->can('create_letter')) {
                        $this->sendMessage($chatId, '❌ شما برای ایجاد نامه دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $completionKeywords = ['#انجام', '#شد', '#انجام_شد'];
                    $isCompletion = collect($completionKeywords)->contains(function ($kw) use ($caption) {
                        return mb_strpos($caption, $kw) !== false;
                    });
                    if ($isCompletion) $caption = trim(str_replace($completionKeywords, '', $caption));

                    $ltp = new LetterParser();
                    $dataLetter = $ltp->parse($caption);

                    $record = Letter::create([
                        'subject' => $dataLetter['title'],
                        'created_at' => $dataLetter['title_date'] ?? Carbon::now(),
                        'description' => $caption,
                        'summary' => $dataLetter['summary'],
                        'mokatebe' => $dataLetter['mokatebe'],
                        'daftar_id' => $dataLetter['daftar'],
                        'kind' => $dataLetter['kind'],
                        'user_id' => $user->id,
                        'peiroow_letter_id' => $dataLetter['pirow'],
                        'status' => $isCompletion ? 1 : 2,
                    ]);

                    if ($dataLetter['kind'] == 1 ){
                        $record->organ_id = $dataLetter['organ_id'];
                        $record->save();
                    }else{
                        $record->organs_owner()->attach($dataLetter['organ_id']);
                    }

                    $record->users()->attach($dataLetter['user_id']); //افزودن به کارپوشه
                    $record->organs_owner()->attach($dataLetter['organ_owners']);
                    $record->customers()->attach($dataLetter['customer_owners']);

                    $message = '✉️ اطلاعاعت نامه ذخیره شده'."\n\n";
                    $message .= '[بازکردن در سامانه]('.LetterResource::getUrl('edit',[$record->id]).')' . "\n\n";
                    $message .= $this->CreateLetterMessage($record);
                    $this->sendMessage($chatId,$message);

                    if (isset($data['message']['document'])) {
                        $doc = $data['message']['document'];
                        $record->update(['file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION)]);
                        Storage::disk('private')->put($record->getFilePath(), $this->getFile($doc['file_id']));
                        if ($media_group_id) {
                            $bale_user->update(['state' => $media_group_id . "_letter_{$record->id}"]);
                        }
                    }
                }

                // ارسال پیام تأیید
                if ($record) {
                    $this->sendMessage($chatId, "ثبت شد ✅ آیدی: {$record->id}");
                }
                return response('ok', 200);
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

    public function CreateLetterMessage(Model $record): string
    {
        $message = '🆔 شماره ثبت : '.$record->id."\n";
        $message .= '❇️ موضوع : '.$record->subject."\n";
        $message .= '📅 تاریخ : '.Jalalian::fromDateTime($record->created_at)->format('Y/m/d')."\n";
        if ($record->summary != '') $message .= '📝 خلاصه (هامش) : '.$record->summary."\n";
        if ($record->mokatebe) $message .= '🔢 شماره مکاتبه : '.$record->mokatebe."\n";
        if ($record->daftar_id) $message .= '🏢 دفتر : '.$record->daftar->name."\n";
        $message .= '📫 صادره یا وارده : '.(($record->kind == 1) ? 'صادره' : 'وارده')."\n";
        if ($record->user) $message .= '👤 کاربر ثبت کننده : '.$record->user->name."\n";
        if ($record->peiroow_letter_id) $message .= '📧 پیرو : '.$record->peiroow_letter_id.'-'.$record->letter->subject."\n";
        if ($record->organ_id) $message .= '📨 گیرنده نامه : '.$record->organ->name."\n";

        $cratablename = '';
        foreach ($record->users as $cartablu){
            $cratablename .= $cartablu->name . ' ، ';
        }
        if ($cratablename != '') $message .= '🗂️ افزوده شده به کارپوشه : '.$cratablename."\n";

        $owners_name = '';
        foreach ($record->customers as $customer){
            $owners_name .= ($customer->code_melli ??  'بدون کد ملی' ).' - '. ($customer->name ?? 'بدون نام') . ' ، ';
        }
        foreach ($record->organs_owner as $organ_owner){
            $owners_name .= $organ_owner->name . ' ، ';
        }
        if ($owners_name != '') $message .= '💌 صاحب : '.$owners_name."\n";

        return $message;
    }

    public function createMinuteMessage(Model $record,$user,$withTasks = true): string
    {
        $message = '[بازکردن در سامانه]('. MinutesResource::getUrl('edit',[$record->id]).')' . "\n\n";
        $message .= "📝 عنوان: {$record->title}\n";
        $message .= "🆔 شماره ثبت: {$record->id}\n";
        $message .= "ℹ️ تعداد کار ها: {$record->tasks->count()}/{$record->tasks->where('completed', 1)->count()}\n";
        if ($user->can('restore_any_minutes') and $record->typer) $message .= "👤 نویسنده: {$record->typer->name}\n";
        if ($record->date) {
            $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($record->date)->format('Y/m/d') . "\n";
        }
        if ($withTasks and $record->tasks->count() != 0){
            $message .= "🧰 کار های صورت جلسه : ";
            $message .= "\n";
            foreach ($record->tasks as $task) {
                $message .= "  " . ($task->completed ? '✅' : '❌') . " " . $task->id . " - " . $task->name ."\n";
            }
        }

        return $message;
    }

    private function getMimeTypeFromExtension($filename): string
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

    private function sendMessage($chatId, $text): void
    {
        $token = env('BALE_BOT_TOKEN');

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        Http::post("https://tapi.bale.ai/bot{$token}/sendMessage", $payload);
    }

    private function sendDocumentByUrl($chatId, $fileUrl, $caption = null): void
    {
        $token = env('BALE_BOT_TOKEN');

        $payload = [
            'chat_id' => $chatId,
            'document' => $fileUrl,
        ];

        if ($caption) {
            $payload['caption'] = $caption;
        }

        Http::post("https://tapi.bale.ai/bot{$token}/sendDocument", $payload);
    }

    private function sendDocumentFromContent($chatId, $fileContent, $filename = 'file.pdf', $mimeType = 'application/pdf', $caption = null): void
    {
        $token = env('BALE_BOT_TOKEN');

        $url = "https://tapi.bale.ai/bot{$token}/sendDocument";

        $response = Http::attach(
            'document', $fileContent, $filename
        )->asMultipart()->post($url, [
            'chat_id' => $chatId,
            'caption' => $caption,
        ]);
    }
    private function sendMessageWithReplyKeyboard($chatId, $text): void
    {
        $token = env('BALE_BOT_TOKEN');

        $keyboard = [
            'keyboard' => [
                [
                    '/راهنما',
                    '/آمار'
                ],
                [
                    '/نامه',
                    '/صورتجلسه',
                    '/کار'
                ],
                [
                    '/ارجاع',
                    '/کارپوشه'
                ],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
        ];

        Http::post("https://tapi.bale.ai/bot{$token}/sendMessage", $payload);
    }


        public function sendNotifBale($user_id, $message)
    {
        $bale_user = BaleUser::query()->where('user_id', $user_id)->first();
        if ($bale_user) {
            $this->sendMessage($bale_user->bale_id, $message);
        }
    }

    private function getFile($filePath)
    {
        $token = env('BALE_BOT_TOKEN');

        return file_get_contents("https://tapi.bale.ai/file/bot{$token}/{$filePath}");
    }

    private function sendMessageWithKeyboard($chatId, $text, $keyboard): void
    {
        $token = env('BALE_BOT_TOKEN');
        Http::post("https://tapi.bale.ai/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function handleCallbackQuery(Request $request): void
    {
        $token = env('BALE_BOT_TOKEN');
        $data = $request->input('callback_query');

        $chatId = $data['message']['chat']['id'];
        $messageId = $data['message']['message_id'];
        $callbackData = $data['data'];

        if (str_starts_with($callbackData, 'letter_page_')) {
            $page = (int) str_replace('letter_page_', '', $callbackData);
            $perPage = 5;

            $query = Letter::query()->orderByDesc('id');
            $totalPages = ceil($query->count() / $perPage);
            $letters = $query->forPage($page, $perPage)->get();

            $message = "🗂 لیست نامه‌های شما - صفحه {$page} از {$query->count()}:\n\n";
            foreach ($letters as $letter) {
                $message .= "📝 عنوان: {$letter->subject}\n";
                $message .= "🆔 شماره ثبت: {$letter->id}\n";
                if ($letter->created_at) {
                    $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($letter->created_at)->format('Y/m/d') . "\n";
                }
                $message .= '[بازکردن در سامانه]('.LetterResource::getUrl('edit',[$letter->id]).')' . "\n";
                $message .= "----------------------\n";
            }

            $keyboard = ['inline_keyboard' => []];
            $buttons = [];

            if ($page > 1) {
                $buttons[] = ['text' => '⬅️ قبلی', 'callback_data' => "letter_page_" . ($page - 1)];
            }
            if ($page < $totalPages) {
                $buttons[] = ['text' => '➡️ بعدی', 'callback_data' => "letter_page_" . ($page + 1)];
            }
            if (!empty($buttons)) {
                $keyboard['inline_keyboard'][] = $buttons;
            }

            Http::post("https://tapi.bale.ai/bot{$token}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private function HelpHandler(string $queryText): string
    {
        $message = '';
        if ($queryText != ''){
            if ($queryText == 'کار'){
                $message = <<<TEXT
راهنمای کار

ℹ️ تعریف کار :  کار به معنای هر چیز انجام شدنی مانند مصوبات ، پیگیری ها ، انواع جلسات ، دیدار ها ، نشست ها ، بازدید ها و ... می باشد .

✅ #کار
ساختار آن باید به شکل زیر باشد .
------------------------------
#کار عنوان جلسه ، تاریخ عددی
------------------------------

نکته :  عنوان از دو جهت اهمیت بسزایی دارد .
1️⃣ اول این که با استفاده از آن دستور کار یا پروژه ، تاریخ ، شهر مربوطه و ارگان مربوطه تشخیص داده می شود و شما باید در عنوان در صورت نیاز به ثبت هر کدام از موارد نامبرده شده ، اسم آن ها در متن بیاورید .
به عنوان مثال :
#کار جلسه موردی سرمایه گذار احداث مجموعه اقتصادی در گرگاب با حضور مدیر کل راهداری
به صورت زیر ثبت می شود :
 📌 *عنوان:* جلسه موردی سرمایه گذار احداث مجموعه اقتصادی در گرگاب با حضور مدیر کل راهداری
 🆔 *شماره ثبت:* 4122215
 🕒 *تاریخ:* 1404/03/02
✅ *وضعیت:* انجام شده
📍 *شهر:* گرگاب

📎 نکته : نوشتن تاریخ اجباری نیست و در صورت وارد نکردن تاریخ ، تاریخ روز در نظر گرفته می شود . در ضمن حتما تاریخ باید به صورت عددی و مانند نمونه قید شود.

2️⃣ دوم این که عنوان مناسب به ارائه گزارش بهتر در آینده کمک می کند .

✅ /کار
این دستور ۵ کار آخر مربوط به کاربر احراز هویت شده را ارسال می کند .
می‌ توان بایه فاصله بعد از دستور عنوان کار یا شماره ثبت کار را وارد کرد تا با استفاده از آن در صورت جلسه ها جستجو شود .

اگر بعد از عنوان یا شماره ثبت که برای جستجو استفاده می شود از #انجام استفاده نمایید وضعیت آن کار به انجام شده تغییر می باید .
TEXT;

            }elseif ($queryText == 'صورتجلسه'){
                $message = <<<TEXT
راهنمای صورت جلسه

✅ #صورتجلسه
این دستور باید در کپشن (زیر یک تصویر یا فایل ) نوشته شود و ساختار آن باید به شکل زیر باشد .
------------------------------
🖼️ تصویر یا فایل صورت جلسه
#صورتجلسه عنوان جلسه ، تاریخ عددی

- عنوان مصوبه مهم اول @مسئول تا یک یا دو روز یا ماه یا سال
- عنوان مصوبه چندم با مسئولیت نام ارگان مربوطه

امضا ها : @نام_ارگان_اول @نام_ارگان_چندم

------------------------------

ℹ️ تعاریف هر بخش :

عنوان :
باید در اولین خط کپشن فایل صورت جلسه آورده شود با #صورتجلسه
عنوان باید با عنوان جلسه ای که در ایتا بارگزاری شده است شباهت داشته باشد . (از نظر لغوی ، نه مفهومی )
 تاریخ باید در عنوان باشد اگر تاریخ نباشد در عنوان ، تاریخ جلسه ثبت شده ، یا تاریخ روز در نظر گرفته می شود .
تاریخ ها باید به صورت عددی نوشته شود به عنوان نمونه ۱۴۰۴/۵/۶

مصوبات :
در خط های بعدی آورده شود و هر مصوبه در یک خط جدا گانه که با ( - ) (خط تیره) یا ( _ ) (زیر خط ) شروع شود .
توی هر خط می توان از یک @ برای تایین مسئول پیگیری اون کار در نطر گرفت مثل @خیری یا @قدسیه یا @طلبی یا @طالبی و... ( باید توجه داشت که در مصوباتی که دستگاه اجرایی مسئول انجام آن است @ به معنای فردی است که باید پیگیری کند آن کار توسط ارگان مربوطه انجام شده است یا خیر ) اگر تعریف نشود شخصی که صورت جلسه را فرستاده به عنوان پیگیری کننده در نظر می گیرد.
اگر اسم ارگانی که مسئول انجام آن مصوبه است در متن باشد تشخیص داده می شود و ثبت می شود. اگر نباشد ارگانی در نظر گرفته نمی‌شود.

امضا ها :
باید اسم ارگان مربوطه با @ در انتهای توضیحات اضافه گردد و به جای فاصله بین کلمات یک ارگان باید از _ استفاده کرد . به عنوان مثال اگر بخواهیم دو امضا کننده راه و شهر سازی استان و اداره راه داری استان را داشته باشم باید به صورت زیر اضافه کنیم :
@راه_و_شهرسازی_استان @راهداری_استان



📎 نکته :
باید خبر جلسه قبل از بارگزاری صورت جلسه در کانال ایتای جناب آقای حاجی بارگزاری شده باشد تا صورت جلسه زمیمه آن جلسه شود .


✅ /صورتجلسه
این دستور ۵ صورت جلسه آخر مربوط به کاربر احراز هویت شده را ارسال می کند .
می‌ توان بایه فاصله بعد از دستور عنوان صورتجلسه یا شماره صورتجلسه را وارد کرد تا با استفاده از آن در صورت جلسه ها جستجو شود .
TEXT;

            }elseif ($queryText == 'نامه'){
                $message = <<<TEXT
راهنمای نامه

✅ #نامه
این دستور باید در کپشن (زیر یک تصویر یا فایل ) نوشته شود و ساختار آن باید به شکل زیر باشد .
------------------------------
🖼️ تصویر یا فایل نامه
#نامه (شماره مکاتبه فیزیکی ) (از یا به ) نام سازمان ، موضوع نامه ، تاریخ عددی

*دفتر* نام دفتر
((*پیرو* شماره ثبت نامه ) یا (*پیرو مکاتبه* شماره مکاتبه فیزیکی ثبت شده) )
@نظری @طالبی @طلبی
= صاحب ارگان
=شخص صاحب شخص

------------------------------

ℹ️ تعاریف هر بخش :

موضوع :
باید در اولین خط کپشن فایل صورت جلسه آورده شود با #نامه شروع شود . در ادامه به صورت اختیاری می توان شماره مکاتبه فیزیکی نامه را آورد .
در ادامه اگر نامه وارده است باید از کلمه ( * از * ) و اگر صادره است از کلمه ( *به* ) و بعد از هر کدام این کلمات نام ارگان مربوطه . در ادامه موضوع نامه همراه با تاریخ مکاتبه آورده می شود .
 تاریخ باید در عنوان باشد اگر تاریخ نباشد در عنوان تاریخ روز در نظر گرفته می شود .
تاریخ ها باید به صورت عددی نوشته شود به عنوان نمونه ۱۴۰۴/۵/۶

☑️ دفتر
با نوشتن کلمه دفتر و بعد از آن نام دفتر مربوطه، نام دفتر تشخیص داده می شود و در سامانه ثبت می شود .به عنوان مثال
دفتر شاهین شهر یا دفتر تهران
نکته : می‌توان نام دفتر را ننوشت، در آن صورت دفتر تهران به طور پیش فرض در نظر گرفته می شود .
☑️ @
اگر نیاز هست نامه به کارتابل اشخاصی اضافه گردد کافی است درخط های بعدی کپشن از @نام استفاده کرد . به عنوان مثال
@طالبی @نظری

☑️ =
با استفاده از کاراتر مساوی در ابتدای خط های بعدی می‌توان صاحب نامه را مشخص کرد . تعداد صاحب های نامه محدودیت تعداد ندارد و به تعداد آن ها می توان در خط های مجازا = گذاشت .
اگر صاحب یه ارگان است بعد از مساوی نام آن ارگان را نوشت و اگر یه صاحب یه شخص حقیق است باید بعد از مساوی کلمه شخص و کد ملی  نام و نام خانوادگی آورده شود . مانند مثال زیر
=شخص 5100248724 محمدمهدی حق شناس

نکته : اگر می دانید که اطلاعات شخص قبلا ثبت شده است در سامانه نوشتن کد ملی کافی است .

☑️ +
کاراکتر + باید ابتدای خط بیاید و بعد از آن هامش نامه را نوشت.

✅ /نامه
این دستور ۵ نامه آخر مربوط به کاربر احراز هویت شده را ارسال می کند .
می‌ توان بایه فاصله بعد از دستور عنوان نامه یا شماره ثبت نامه را وارد کرد تا با استفاده از آن در نامه ها جستجو شود .
TEXT;
            }
        }else{
            $message = <<<TEXT
ℹ️ راهنمای ربات

دستوراتی که با / شروع می‌شوند برای اطلاعات موجود در سامانه هستند و دستوراتی که با # شروع می شوند برای ثبت اطلاعات جدید در سامانه هستند .

✅ لیست دستورات نمایش اطلاعات :
/صورتجلسه
نمایش 5 صورت جلسه آخر شما
/کار
نمایش 5 کار آخر شما
/نامه
نمایش 5 نامه آخر شما
/راهنما
این دستور راهنمای ربات را ارسال می کند . در ادامه دستور عنوان هر موجودیت شامل کار ، نامه ، صورتجلسه و... را وارد کنید راهنمای آن موجودیت را برای شما ارسال می کند.

✅ لیست دستورات ثبتی :
#صورتجلسه
ایجاد صورت جلسه
#کار
ایجاد کار شامل جلسه ، مصوبه ، پیگیری ، بازدید و ...
#نامه
ایجاد نامه


⚠️ توجه !
ربات به فاصله ها (اسپیس یا فضای خالی) بین کلمات و دستورات حساس می باشد.  به عنوان مثال ( # صورت جلسه ) یا (/ کار ) اشتباه است و شکل صحیح آن ( #صورتجلسه ) یا (/کار) می باشد .
TEXT;

        }
        return $message;
    }
}
