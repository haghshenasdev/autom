<?php

namespace App\Http\Controllers;

use App\Filament\Resources\LetterResource;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\TaskResource;
use App\Http\Controllers\ai\CategoryPredictor;
use App\Http\Controllers\ai\LetterParser;
use App\Models\AppendixOther;
use App\Models\Cartable;
use App\Models\City;
use App\Models\Organ;
use App\Models\Project;
use App\Models\Referral;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
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
            $isPrivateChat = isset($data['message']['chat']['type']) && $data['message']['chat']['type'] == "private";
//            $this->sendMessage($chatId, json_encode($data));

            if(is_null($userMessage)) return null;
            // احراز هویت کاربر
            $auth_res = $this->authBale($userMessage,$chatId,$text);
            if (is_string($auth_res)){
                return response($auth_res);
            }
            [$user,$bale_user] = $auth_res;


            if ($media_group_id) {
                $doc = $data['message']['document'];
                if ($caption == '') {
                    $bale_user->update(['state' => $media_group_id . '_' . $doc['file_id'] . '_' . pathinfo($doc['file_name'], PATHINFO_EXTENSION)]);
                }

            }

            if ($text != '') {
                $text = trim(CalendarUtils::convertNumbers($text, true)); // حذف فاصله‌های اضافی و تبدیل اعداد فارسی
                $lines = explode("\n", $text);
                $firstLine = $lines[0] ?? '';
                $secondLine = $lines[1] ?? '';

                if (str_starts_with($firstLine, '/کارپوشه')) {
                    if (!$user->can('view_cartable')) {
                        $this->sendMessage($chatId, '❌ شما به کارپوشه دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $queryText = trim(str_replace('/کارپوشه', '', $firstLine));
                    $completionKeywords = ['#انجام', '#شد', '#انجام_شد', '#بررسی'];
                    $isCompletion = collect($completionKeywords)->contains(function ($kw) use ($text) {
                        return mb_strpos($text, $kw) !== false;
                    });
                    if ($isCompletion) $queryText = trim(str_replace($completionKeywords, '', $queryText));
                    $completionKeywords = ['#همه',];
                    $isFilter = collect($completionKeywords)->contains(function ($kw) use ($text) {
                        return mb_strpos($text, $kw) !== false;
                    });
                    if ($isFilter) $queryText = trim(str_replace($completionKeywords, '', $queryText));

                    $query = Cartable::query()->where('user_id', $user->id);

                    if (is_numeric($queryText)) {
                        $query->WhereHas('letter', function ($query) use ($queryText) {
                            $query->where('id', $queryText);
                        });
                    } elseif ($queryText !== '') {
                        $query->WhereHas('letter', function ($query) use ($queryText) {
                            $query->where('subject', 'like', "%{$queryText}%");
                        });
                    } else {
                        $query->orderByDesc('id');
                    }

                    if (!$isFilter) {
                        $query->where('cartables.checked', '=', null);
                    }

                    $letters = $query->limit(5)->get();

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
                        $message .= $this->createCartableMessage($letter);
                        $message .= '[بازکردن در سامانه](' . LetterResource::getUrl('edit', [$letter->letter->id]) . ')' . "\n";
                        $message .= "----------------------\n";
                    }

                    $this->sendMessage($chatId, $message);
                    return response('کارپوشه ارسال شد');

                }
                elseif (str_starts_with($firstLine, '/start')) {
                    $this->sendMessageWithReplyKeyboard($chatId, "🌺 سلام {$user->name} ، به ربات کارنما خوش آمدید !" . "\n" . "من میتونم به شما کمک کنم بتوانید به راحتی و سریع ترین حالت ممکن از سامانه کارنما استفاده کنید و کار ها و صورت جلسه های خود را مدیریت کنید." . "\n" . "با ارسال دستور /راهنما می توانید لیست دستورات کار با ربات را دریافت نمایید .");
                    return response('احراز شده');
                }
                elseif (str_starts_with($firstLine, '/ارجاع')) {
                    if (!$user->can('view_referral')) {
                        $this->sendMessage($chatId, '❌ شما به ارجاع ها دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $queryText = trim(str_replace('/ارجاع', '', $firstLine));
                    $completionKeywords = ['#انجام', '#شد', '#انجام_شد', '#بررسی'];
                    $isCompletion = collect($completionKeywords)->contains(function ($kw) use ($text) {
                        return mb_strpos($text, $kw) !== false;
                    });
                    if ($isCompletion) $queryText = trim(str_replace($completionKeywords, '', $queryText));

                    $query = Referral::query()->where('to_user_id', $user->id);

                    if (is_numeric($queryText)) {
                        $query->WhereHas('letter', function ($query) use ($queryText) {
                            $query->where('id', $queryText);
                        });
                    } elseif ($queryText !== '') {
                        $query->WhereHas('letter', function ($query) use ($queryText) {
                            $query->where('subject', 'like', "%{$queryText}%");
                        });
                    } else {
                        $query->orderByDesc('id');
                    }

                    $letters = $query->limit(5)->get();

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
                        $message .= $this->CreateReferralMessage($letter);
                        $message .= '[بازکردن در سامانه](' . LetterResource::getUrl('edit', [$letter->letter->id]) . ')' . "\n";
                        $message .= "----------------------\n";
                    }

                    $this->sendMessage($chatId, $message);
                    return response('ارجاع ارسال شد');

                }
                elseif (str_starts_with($firstLine, '/کار')) {

                    $handle_res = $this->handleEntry($chatId,$user,'کار','view_task',
                        $firstLine,
                        Task::query(),
                        'restore_any_task',
                        anyQuery: function ($query) use ($user) {
                            $query->where('Responsible_id', $user->id);
                        },
                        searchField: 'name',
                        useIsCompletion: true,
                        filterQuery: function ($query) use ($user,$secondLine) {
                            if ($secondLine != '' and str_starts_with($secondLine , 'صورتجلسه')) {
                                $queryMinText = trim(str_replace('صورتجلسه','',$secondLine));
                                if (is_numeric($queryMinText)) {
                                    $query->where('minutes_id', $queryMinText);
                                } else {
                                    $minute = Minutes::query()->where('title', 'like', "%{$queryMinText}%")->first();
                                    if ($minute) $query->where('minutes_id', $minute->id);
                                }
                            }
                        }
                    );
                    // اگر خروجی response بود → همون رو برگردون
                    if (is_string($handle_res)) {
                        return response($handle_res);
                    }
                    [$query, $queryText,$tasks,$isCompletion] = $handle_res;


                    if (count($tasks) == 1) {
                        foreach ($tasks as $task) {
                            if ($isCompletion && !$task->completed) {
                                $task->completed = 1;
                                $task->completed_at = now();
                                $description = trim(str_replace($firstLine, '', $text));
                                if ($description != '') $task->description = $description;
                                $task->save();
                                $message .= "🔁 وضعیت کار «{$task->name}» به انجام‌شده تغییر یافت.\n\n";
                            }

                            $message .= $this->CreateTaskMessage($task, $user);
                            $message .= "\n" . '[بازکردن در سامانه](' . TaskResource::getUrl('edit', [$task->id]) . ')' . "\n\n";
                            $message .= "----------------------\n";
                        }

                        $this->sendMessage($chatId, $message);
                    }else{
                        $this->paginateAndSend($chatId, $query, $queryText, 1, 5, 'کار', $user);
                    }
                    return response('کار ارسال شد');

                }
                elseif (str_starts_with($firstLine, '/دستورکار') or str_starts_with($firstLine, '/دستور کار')) {
                    if (!$user->can('view_project')) {
                        $this->sendMessage($chatId, '❌ شما به دستورکار ها دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $queryText = trim(str_replace(['/دستورکار','/دستور کار'], '', $firstLine));

                    $query = Project::query();

                    if (is_numeric($queryText)) {
                        $query->where('id', $queryText);
                    } elseif ($queryText !== '') {
                        $query->where('name', 'like', "%{$queryText}%");
                    } else {
                        $query->orderByDesc('id');
                    }

                    if (!$user->can('restore_any_project')) {
                        $query->where('user_id', $user->id);
                    }

                    $records = $query->limit(5)->get();

                    if ($records->isEmpty()) {
                        $this->sendMessage($chatId, '📭 هیچ دستورکاری مطابق با جستجوی شما یافت نشد.');
                        return response('دستورکار خالی');
                    }

                    $message = $queryText ? "🔍 نتیجه جستجو برای «{$queryText}»:\n\n" : "لیست آخرین دستورکارهای شما:\n\n";

                    foreach ($records as $record) {
                        $message .= $this->createProjectMessage($record, $user);
                        $message .= "\n" . '[بازکردن در سامانه](' . ProjectResource::getUrl('edit', [$record->id]) . ')' . "\n\n";
                        $message .= "----------------------\n";
                    }

                    $this->sendMessage($chatId, $message);
                    return response('دستورکار ارسال شد');

                }
                elseif (str_starts_with($firstLine, '/ارگان')) {

                    $queryText = trim(str_replace('/ارگان', '', $firstLine));

                    $query = Organ::query();

                    if (is_numeric($queryText)) {
                        $query->where('id', $queryText);
                    } elseif ($queryText !== '') {
                        $query->where('name', 'like', "%{$queryText}%");
                    } else {
                        $query->orderByDesc('id');
                    }

                    if (!$user->can('restore_any_project')) {
                        $query->where('user_id', $user->id);
                    }

                    $records = $query->limit(5)->get();

                    if ($records->isEmpty()) {
                        $this->sendMessage($chatId, '📭 هیچ ارگانی مطابق با جستجوی شما یافت نشد.');
                        return response('ارگان خالی');
                    }

                    $message = $queryText ? "🔍 نتیجه جستجو برای «{$queryText}»:\n\n" : "لیست آخرین ارگان ها :\n\n";

                    foreach ($records as $record) {
                        $message .= 'ثبت : ' . $record->id ."\n";
                        $message .= 'نام : ' . $record->name ."\n";
                        if ($record->organ_type_id) $message .= 'نوع : ' . $record->type->name ."\n";
                        $message .= "----------------------\n";
                    }

                    $this->sendMessage($chatId, $message);
                    return response('ارگان ارسال شد');

                }
                elseif (str_starts_with($firstLine, '#مصوبه')){
                    $queryText = trim(str_replace('#مصوبه', '', $firstLine));

                    if (is_numeric($queryText)){
                        $minute = Minutes::query()->where('id', $queryText)->first();
                        if ($minute){
                            $mp = new \App\Http\Controllers\ai\MinutesParser(false);
                            $parsedData = $mp->parse($text, $user->id,Carbon::createFromTimestamp($minute->date));
                            if (count($parsedData['approves']) != 0) {
                                $message = 'مصوبات زیر به صورتجلسه "' . $minute->title . '" اضافه شد .' . "\n\n";

                                foreach ($parsedData['approves'] as $approve) {
                                    $cp = new \App\Http\Controllers\ai\CategoryPredictor();
                                    $keywords = $cp->extractKeywords($approve['text']);
                                    $task = Task::create([
                                        'name' => $approve['text'],
                                        'started_at' => $minute->date,
                                        'created_at' => $minute->date,
                                        'amount' => $approve['amount'],
                                        'ended_at' => $approve['due_at'] ?? null,
                                        'Responsible_id' => $approve['user']['id'] ?? $user->id,
                                        'created_by' => $user->id,
                                        'minutes_id' => $minute->id,
                                        'city_id' => $cp->detectCity($keywords),
                                        'organ_id' => $cp->detectOrgan($keywords),
                                    ]);
                                    $task->group()->attach([33, 32]); // دسته بندی هوش مصنوعی و مصوبه
                                    $task->project()->attach($approve['projects']);

                                    //ایجاد پیام
                                    $message .= $this->CreateTaskMessage($task, $user);
                                    $message .= "\n" . '[بازکردن در سامانه](' . TaskResource::getUrl('edit', [$task->id]) . ')' . "\n\n";
                                    $message .= "----------------------\n";
                                }

                                if (!$isPrivateChat){
                                    $message = '📋 ['.count($parsedData['approves']).' مصوبه به صورتجلسه "'.$minute->title.'" اظافه شد .]('. MinutesResource::getUrl('edit',[$minute->id]).')';
                                }
                                $this->sendMessage($chatId, $message);
                                return response('مصوبه ها ایجاد شدند');
                            }else{
                                $this->sendMessage($chatId,'مصوبه ای برای افزودن یافت نشد !');
                            }
                        }
                        else{
                            $this->sendMessage($chatId,'صورت جلسه مورد نظر یافت نشد .');
                        }
                    }else{
                        $this->sendMessage($chatId,'لطفا بعد از #مصوبه شماره ثبت صورتجلسه مورد نظر را یاداشت کنید .');
                    }

                }
                elseif (str_starts_with($firstLine, '/صورتجلسه')) {
                    $handle_res = $this->handleEntry($chatId,$user,'صورتجلسه','view_minutes',
                        $firstLine,
                        Minutes::query(),
                        'restore_any_minutes',
                        anyQuery: function ($query) use ($user) {
                            $query->where('typer_id', $user->id);
                        },
                        searchField: 'title'
                    );
                    // اگر خروجی response بود → همون رو برگردون
                    if (is_string($handle_res)) {
                        return response($handle_res);
                    }

                    [$query, $queryText,$minutes] = $handle_res;

                    if (count($minutes) == 1) {
                        $message = '[بازکردن در سامانه](' . MinutesResource::getUrl('edit', [$minutes[0]->id]) . ')' . "\n\n";
                        $message .= $this->createMinuteMessage($minutes[0],$user);
                        $path = $minutes[0]->getFilePath();
                        $this->sendDocumentFromContent($chatId, Storage::disk('private_appendix_other')->get($path), basename($path), $this->getMimeTypeFromExtension($path), $message);
                    } else {
                        $this->paginateAndSend($chatId, $query, $queryText, 1, 5, 'صورتجلسه', $user);
                    }

                    return response('صورت‌جلسه ارسال شد');

                }
                elseif (str_starts_with($firstLine, '#نامه')) {
                    if (str_contains($text, '#متن')) {
                        $ltp = new LetterParser();
                        $dataLetter = $ltp->mixedParse($text);
                        $this->sendMessage($chatId, 'متن زیر را اصلاح کنید و زیر یک تصویر ارسال نمایید .');
                        $this->sendMessage($chatId, $ltp->rebuildText($dataLetter));
                    }elseif (isset($data['message']['reply_to_message']['document']['file_id'])) {
                        $reply_msg = $data['message']['reply_to_message'];
                        $doc = $reply_msg['document'];
                        $record = $this->handleLetter_create($text,$chatId,$user,$isPrivateChat);

                        $this->LetterFileAdd($record,$doc,$media_group_id,$bale_user);
                    }
                }
                elseif (str_starts_with($firstLine,'#صورتجلسه')) {
                    if (isset($data['message']['reply_to_message']['document']['file_id'])) {
                        $reply_msg = $data['message']['reply_to_message'];
                        $doc = $reply_msg['document'];
                        $record = $this->handleMinute_create($text, $chatId, $user,$isPrivateChat,$doc);

                        $this->MinuteFileAdd($record,$doc,$media_group_id,$bale_user);
                    }else {
                        $this->sendMessage($chatId,'لطفا این متن را در پاسخ یک فایل برام بفرست تا فایل صورتجلسه را در سامانه ثبت کنم');
                    }
                }
                elseif (str_starts_with($firstLine, '/نامه')) {
                    $handle_res = $this->handleEntry($chatId,$user,'نامه','view_letter',
                        $firstLine,
                        Letter::query(),
                        'restore_any_letter',
                        anyQuery: function ($query) use ($user) {
                            $query->orWhere('user_id', $user->id)->orWhereHas('referrals', function ($quer) use ($user) {
                                $quer->where('to_user_id', $user->id); // نامه‌هایی که Referral.to_user_id برابر با آیدی کاربر لاگین شده است
                            })->orWhereHas('users', function ($query) use ($user) {
                                $query->where('user_id', $user->id);
                            });
                        },
                        searchField: 'subject'
                    );
                    // اگر خروجی response بود → همون رو برگردون
                    if (is_string($handle_res)) {
                        return response($handle_res);
                    }

                    [$query, $queryText,$letters] = $handle_res;

                    if (count($letters) == 1) {
                        $message = '[بازکردن در سامانه](' . LetterResource::getUrl('edit', [$letters[0]->id]) . ')' . "\n\n";
                        $message .= $this->CreateLetterMessage($letters[0]);
                        $path = $letters[0]->getFilePath();
                        $this->sendDocumentFromContent($chatId, Storage::disk('private')->get($path), basename($path), $this->getMimeTypeFromExtension($path), $message);
                    } else {
                        $this->paginateAndSend($chatId, $query, $queryText, 1, 5, 'نامه', $user);
                    }

                    return response('نامه ارسال شد');
                }
                elseif (str_starts_with($firstLine, '#کار') or str_starts_with($firstLine, '#جلسه')) {

                    $task = $this->handleTasks_create($text,$user,$chatId,$isPrivateChat);

                    if ($task){
                        // ضمیمه کردن ریپلای
                        if (isset($data['message']['reply_to_message']['document']['file_id'])){
                            $reply_msg = $data['message']['reply_to_message'];
                            $doc = $reply_msg['document'];
                            $appendix = AppendixOther::withoutEvents(function () use ($task,$doc,$reply_msg) {
                                return $task->appendix_others()->create([
                                    'title'       => 'ضمیمه',
                                    'description' => $reply_msg['caption'] ?? null,
                                    'file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION),
                                ]);
                            });
                            Storage::disk('private_appendix_other')->put($appendix->getFilePath(), $this->getFile($doc['file_id']));
                        }
                    }

                    return response("Task ذخیره شد: ");
                }
                elseif (str_starts_with($firstLine, '#گزارش')) {

                    $task = $this->handleTasks_create($text,$user,$chatId,$isPrivateChat,[36]);

                    if ($task){
                        // ضمیمه کردن ریپلای
                        if (isset($data['message']['reply_to_message']['document']['file_id'])){
                            $reply_msg = $data['message']['reply_to_message'];
                            $doc = $reply_msg['document'];
                            $appendix = AppendixOther::withoutEvents(function () use ($task,$doc,$reply_msg) {
                                return $task->appendix_others()->create([
                                    'title'       => 'ضمیمه',
                                    'description' => $reply_msg['caption'] ?? null,
                                    'file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION),
                                ]);
                            });
                            Storage::disk('private_appendix_other')->put($appendix->getFilePath(), $this->getFile($doc['file_id']));
                        }
                    }

                    return response("Task ذخیره شد: ");
                }
                elseif (str_starts_with($firstLine, '/راهنما')) {
                    $queryText = trim(str_replace('/راهنما', '', $firstLine));
                    $message = $this->HelpHandler($queryText);

                    $this->sendMessage($chatId, $message);
                    return response("راهنما ارسال شد .");
                }
                elseif (str_starts_with($firstLine, '/آمار')) {
                    $message = "📈 آمار \n\n";
                    $message .= "📄 نامه های شما : " . Letter::query()->whereHas('users', function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        }) // نامه‌هایی که user_id برابر با آیدی کاربر لاگین شده است
                        ->orWhereHas('referrals', function ($query) use ($user) {
                            $query->where('to_user_id', $user->id); // نامه‌هایی که Referral.to_user_id برابر با آیدی کاربر لاگین شده است
                        })->count() . "\n";
                    $message .= "↖️ ارجاع بررسی نشده : " . Referral::query()->where('to_user_id', $user->id)->whereNot('checked', 1)->count() . "\n";
                    $message .= "🧰  کار پوشه بررسی نشده : " . Cartable::query()->where('user_id', $user->id)->whereNot('checked', 1)->count() . "\n";
                    $message .= "ℹ️ دستورکار های شما : " . Project::query()->where('user_id', $user->id)->count() . "\n";
                    $message .= "🕹️ کار های شما : " . Task::query()->where('Responsible_id', $user->id)->count() . "\n";
                    $message .= "📝 صورت جلسه های شما : " . Minutes::query()->where('typer_id', $user->id)->count();

                    $this->sendMessage($chatId, $message);
                    return response("آمار ارسال شد .");
                }
                else if ($isPrivateChat) {
                    $this->HandleAiChat($chatId, $text);
                }

            } elseif ($caption != '') {
                $caption = CalendarUtils::convertNumbers($caption, true); // تبدیل اعداد فارسی به انگلیسی
                // تشخیص هشتگ‌ها
                $hashtags = ['#صورتجلسه', '#صورت', '#صورت-جلسه', '#نامه', '#کار', '#جلسه', '#گزارش'];
                $matched = collect($hashtags)->filter(fn($tag) => str_contains($caption, $tag))->first();


                // ذخیره در مدل مناسب
                $record = null;
                if (in_array($matched, ['#صورتجلسه', '#صورت', '#صورت-جلسه'])) {
                    $record = $this->handleMinute_create($caption,$chatId,$user,$isPrivateChat,$data['message']['document'] ?? null);

                    if (isset($data['message']['document'])) {
                        $doc = $data['message']['document'];
                        $this->MinuteFileAdd($record,$doc,$media_group_id,$bale_user);
                    }
                    return response('صورت جلسه ایجاد شد.');

                }
                elseif ($matched === '#نامه') {
                    if (str_contains($caption, '#متن')) {
                        $ltp = new LetterParser();
                        $dataLetter = $ltp->mixedParse($caption);
                        $this->sendMessage($chatId, 'متن زیر را اصلاح کنید و زیر یک تصویر ارسال نمایید .');
                        $this->sendMessage($chatId, $ltp->rebuildText($dataLetter));
                    } else {
                        $record = $this->handleLetter_create($caption,$chatId,$user,$isPrivateChat);

                        if (isset($data['message']['document'])) {
                            $doc = $data['message']['document'];

                            $this->LetterFileAdd($record,$doc,$media_group_id,$bale_user);
                        }
                    }
                }
                elseif (in_array($matched, ['#کار', '#جلسه'])){
                    $task = $this->handleTasks_create($caption,$user,$chatId,$isPrivateChat);

                    if ($task){
                        // ضمیمه کردن فایل
                        if (isset($data['message']['document']['file_id'])){
                            $doc = $data['message']['document'];
                            $appendix = AppendixOther::withoutEvents(function () use ($task,$doc) {
                                return $task->appendix_others()->create([
                                    'title'       => 'ضمیمه',
                                    'file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION),
                                ]);
                            });
                            Storage::disk('private_appendix_other')->put($appendix->getFilePath(), $this->getFile($doc['file_id']));
                        }
                    }
                }
                elseif (in_array($matched, ['#گزارش'])){
                    $task = $this->handleTasks_create($caption,$user,$chatId,$isPrivateChat,[36]);

                    if ($task){
                        // ضمیمه کردن فایل
                        if (isset($data['message']['document']['file_id'])){
                            $doc = $data['message']['document'];
                            $appendix = AppendixOther::withoutEvents(function () use ($task,$doc) {
                                return $task->appendix_others()->create([
                                    'title'       => 'ضمیمه',
                                    'file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION),
                                ]);
                            });
                            Storage::disk('private_appendix_other')->put($appendix->getFilePath(), $this->getFile($doc['file_id']));
                        }
                    }
                }
                // ارسال پیام تأیید
//                if ($record) {
//                    $this->sendMessage($chatId, "ثبت شد ✅ آیدی: {$record->id}");
//                }
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

    private function handleEntry($chatId, $user, string $title, string $can, $firstLine, Builder $query, string $anyCan, \Closure $anyQuery, string $searchField, $useIsCompletion = false,\Closure $filterQuery = null): array|string
    {
        if (!$user->can($can)) {
            $this->sendMessage($chatId, "❌ شما به {$title} ها دسترسی ندارید!");
            return 'عدم دسترسی';
        }

        $queryText = trim(str_replace("/{$title}", '', $firstLine));
        $isCompletion = null;
        if ($useIsCompletion){
            $completionKeywords = ['#انجام', '#شد', '#انجام_شد'];
            $isCompletion = collect($completionKeywords)->contains(function ($kw) use ($firstLine) {
                return mb_strpos($firstLine, $kw) !== false;
            });
            if ($isCompletion) $queryText = trim(str_replace($completionKeywords, '', $queryText));
        }

        if (is_numeric($queryText)) {
            $query->where('id', $queryText);
        } elseif ($queryText !== '') {
            $query->where($searchField, 'like', "%{$queryText}%");
        }
        $query->orderByDesc('id');

        if (!$user->can($anyCan)) {
            $anyQuery($query);
        }

        if ($filterQuery){
            $filterQuery($query);
        }

        $records = $query->get();

        if ($records->isEmpty()) {
            $this->sendMessage($chatId, "📭 هیچ موردی مطابق با جستجوی شما یافت نشد.");
            return 'خالی';
        }
        $result = [
            $query,
            $queryText,
            $records
        ];
        if ($useIsCompletion) $result[] = $isCompletion;
        return $result;
    }

    public function CreateTaskMessage(Model $record, $user = null): string
    {
        $message = "📝 عنوان: {$record->name}\n";
        $message .= "🆔 شماره ثبت: {$record->id}\n";
        $message .= "ℹ️ وضعیت انجام: " . ($record->completed ? '✅ انجام شده' : '❌ انجام نشده') . "\n";
        if ($user and $user->can('restore_any_task') and $record->responsible) $message .= "👤 مسئول: {$record->responsible->name}\n";
        $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($record->created_at)->format('Y/m/d') . "\n";
        if ($record->completed and $record->completed_at) $message .= "📅 تاریخ انجام: " . Jalalian::fromDateTime($record->completed_at)->format('Y/m/d') . "\n";
        if ($record->ended_at) $message .= "📅 تاریخ پایان: " . Jalalian::fromDateTime($record->ended_at)->format('Y/m/d') . "\n";
        if ($record->city_id) $message .= "📍 شهر : " . $record->city->name . "\n";
        if ($record->project->count() != 0) {
            $message .= "🎚️ دستورکار : ";
            foreach ($record->project as $project) {
                $message .= $project->name . "، ";
            }
            $message .= "\n";
        }
        if ($record->group->count() != 0) {
            $message .= "📚 دسته بندی : ";
            foreach ($record->group as $group) {
                $message .= $group->name . "، ";
            }
            $message .= "\n";
        }
        return $message;
    }

    public function CreateReferralMessage(Model $record): string
    {
        $message = "📝 عنوان: {$record->letter->subject}\n";
        $message .= "🆔 شماره ثبت: {$record->letter->id}\n";
        $message .= "✔️ وضعیت بررسی : " . ($record->checked == 1 ? "✅ بررسی شده" : "❌ بررسی نشده") . "\n";
        if ($record->rule) $message .= "ℹ️ دستور : " . $record->rule . "\n";
        $message .= "↖️ توسط : " . $record->by_users->name . "\n";
        if ($record->letter->created_at) {
            $message .= "📅 تاریخ ثبت نامه: " . Jalalian::fromDateTime($record->letter->created_at)->format('Y/m/d') . "\n";
        }
        if ($record->created_at) {
            $message .= "📅 تاریخ ثبت در کارتابل: " . Jalalian::fromDateTime($record->created_at)->format('Y/m/d') . "\n";
        }
        return $message;
    }

    public function createCartableMessage(Model $record): string
    {
        $message = "📝 عنوان: {$record->letter->subject}\n";
        $message .= "🆔 شماره ثبت: {$record->letter->id}\n";
        $message .= "✔️ وضعیت بررسی : " . ($record->checked == 1 ? "✅ بررسی شده" : "❌ بررسی نشده") . "\n";
        if ($record->letter->created_at) {
            $message .= "📅 تاریخ ثبت نامه: " . Jalalian::fromDateTime($record->letter->created_at)->format('Y/m/d') . "\n";
        }
        if ($record->created_at) {
            $message .= "📅 تاریخ ثبت در کارتابل: " . Jalalian::fromDateTime($record->created_at)->format('Y/m/d') . "\n";
        }

        return $message;
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

        if ($record->projects->count() != 0){
            $message .= "🎚️ دستورکار : ";
            foreach ($record->projects as $project) {
                $message .= $project->name ."، ";
            }
            $message .= "\n";
        }

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
        $message = "📝 عنوان: {$record->title}\n";
        $message .= "🆔 شماره ثبت: {$record->id}\n";
        $message .= "ℹ️ تعداد کار ها: {$record->tasks->count()}/{$record->tasks->where('completed', 1)->count()}\n";
        if ($user->can('restore_any_minutes') and $record->typer) $message .= "👤 نویسنده: {$record->typer->name}\n";
        if ($record->date) {
            $message .= "📅 تاریخ ثبت: " . Jalalian::fromDateTime($record->date)->format('Y/m/d') . "\n";
        }
        if ($record->task_id) {
            $message .= "❇️ جلسه : " . $record->task_creator->name . "\n";
        }
        if ($record->organ->count() != 0){
            $message .= "🏢 امضا کنندگان : ";
            foreach ($record->organ as $organ) {
                $message .= "  "  . $organ->name ."،";
            }
            $message .= "\n";
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

    public function createProjectMessage(Model $record,$user,$description = false): string
    {
        $message = "";

        // شناسه ثبت
        $message .= "🆔 ثبت: {$record->id}\n";

        // عنوان
        $message .= "🎚️ عنوان: {$record->name}\n";

        // توضیحات
        if ($description and !empty($record->description)) {
            $message .= "📝 توضیحات: {$record->description}\n";
        }

        // مسئول
        if ($user->can('restore_any_project') and !empty($record->user?->name)) {
            $message .= "👤 مسئول: {$record->user->name}\n";
        }

        // شهر
        if (!empty($record->city?->name)) {
            $message .= "🏙️ شهر: {$record->city->name}\n";
        }

        // دستگاه اجرایی
        if (!empty($record->organ?->name)) {
            $message .= "🏢 دستگاه اجرایی: {$record->organ->name}\n";
        }

        // وضعیت
        if (!empty($record->status)) {
            $message .= "📊 وضعیت: " . Project::getStatusLabel($record->status) . "\n";
        }

        // اعتبار
        if (!empty($record->amount)) {
            $formattedAmount = number_format($record->amount);
            $message .= "💰 اعتبار: {$formattedAmount} ریال\n";
        }

        // تاریخ ایجاد
        if (!empty($record->created_at)) {
            $message .= "📅 ایجاد: ".Jalalian::fromDateTime($record->created_at)->format('Y/m/d')."\n";
        }

        // دسته بندی
        if ($record->group->count() != 0) {
            $message .= "📚 دسته بندی: ";
            foreach ($record->group as $group) {
                $message .= $group->name . "، ";
            }
            $message = rtrim($message, "، ") . "\n";
        }

        // تعداد کارها
        if (!empty($record->tasks_count)) {
            $message .= "🧾 تعداد کارها: {$record->tasks_count}\n";
        }

        // پیشرفت
        $total = $record->required_amount != null ? $record->required_amount : $record->tasks()->count();
        $progress = $record->tasks()->where('completed', true)->count();
        if ($total > 0) {
            $percent = round(($progress / $total) * 100);
            $message .= "📈 پیشرفت: {$progress}/{$total} ({$percent}%)\n";
        }

        return mb_convert_encoding($message, 'UTF-8', 'UTF-8');
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

    private function sendMessage($chatId, $text): null|string
    {
        $token = env('BALE_BOT_TOKEN');

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        $response = Http::post("https://tapi.bale.ai/bot{$token}/sendMessage", $payload);

        if ($response->successful()) {
            $data = $response->json();
            return $data['result']['message_id'] ?? null;
        }

        return null;
    }

    /**
     * @param mixed $chatId
     * @param string $text
     * @return void
     * @throws Exception
     */
    public function HandleAiChat(mixed $chatId, string $text): void
    {
        $processMessageID = $this->sendMessage($chatId, '🔁 درحال پردازش ...');

        try {

            $references = $this->HelpHandler('')
            ."\n----------------\n";
            if (str_contains($text,'صورتجلسه') || str_contains($text,'صورت جلسه')) {
                $references .= $this->HelpHandler('صورتجلسه');
                $references .= "\n----------------\n";
            }
            if (str_contains($text,'نامه')) {
                $references .= $this->HelpHandler('نامه');
                $references .= "\n----------------\n";
            }
            if (str_contains($text,'کار')) {
                $references .= $this->HelpHandler('کار');
                $references .= "\n----------------\n";
            }

            $content = $this->AiChat(<<<EOT
برام جواب مناسب برای پیام کاربر را با توجه به اطلاعات زیر بفرست، بدون هیچ توضیح اضافی.
این پیام را از طرف ربات کارنما که می تواند به کاربر کمک کند بتوانید به راحتی و سریع ترین حالت ممکن از سامانه کارنما استفاده کند و کار ها و صورت جلسه های خود را مدیریت کنید.

پیام کاربر:
{$text}

اطلاعات :
{$references}

EOT);

            $this->sendMessage($chatId, $content);
        } catch (Exception $exception) {
            $this->sendMessage($chatId, 'متاسفانه ارتباط با هوش مصنوعی با مشکل مواجه شد . لطفا ساعاتی دیگر پیام دهید .');
            throw $exception;
        } finally {
            $this->deleteMessage($chatId,$processMessageID);
        }
    }

    public function AiChat($comMessage)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('GAPGPT_API_KEY'),
        ])->post('https://api.gapgpt.app/v1/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'user', 'content' => $comMessage],
            ],
        ]);

        $content = $response->json('choices.0.message.content');

        //حذف حالت سورس کد
        $content = str_replace("```",'',$content);

        return $content;
    }


    /**
     * @param mixed $token
     * @param mixed $chatId
     * @param mixed $messageId
     * @return void
     */
    public function deleteMessage(string $chatId, string $messageId): void
    {
        $token = env('BALE_BOT_TOKEN');

        Http::post("https://tapi.bale.ai/bot{$token}/deleteMessage", [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
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
                    '/آمار',
                    '/دستورکار',
                ],
                [
                    '/نامه',
                    '/صورتجلسه',
                    '/کار'
                ],
                [
                    '/ارجاع',
                    '/کارپوشه',
                    '/اسناد'
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
        return file_get_contents($this->getFileUrl($filePath));
    }

    private function getFileUrl($filePath): string
    {
        $token = env('BALE_BOT_TOKEN');

        return "https://tapi.bale.ai/file/bot{$token}/{$filePath}";
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
        $data = $request->input('callback_query');

        $chatId = $data['message']['chat']['id'];
        $messageId = $data['message']['message_id'];
        $callbackData = $data['data'];

        // احراز هویت
        $auth_res = $this->authBale($data['from'],$chatId);
        if (is_string($auth_res)){
            return ;
        }
        [$user,$bale_user] = $auth_res;

        // مدیریت حذف پیام
        if ($callbackData === 'delete_message') {
            $this->deleteMessage($chatId, $messageId);
            return;
        }

        if (str_starts_with($callbackData, 'toggle_category|')) {
            // ساختار: toggle_category|model_class|model_id|type_class|type_id
            [, $modelClass, $modelId, $typeClass, $typeId,$filterJson] = explode('|', $callbackData);
            $filterModelTypes = json_decode($filterJson, true);

            $modelsub = app($modelClass)::find($modelId);
            if (!$modelsub) return;

            $relationMap = [
                \App\Models\Project::class => [
                    'relation' => 'project',
                    'foreign_key' => 'project_id',
                    'label' => 'دستور کار ها',
                ],
                \App\Models\TaskGroup::class => [
                    'relation' => 'group',
                    'foreign_key' => 'task_group_id',
                    'label' => 'دسته بندی ها',
                ],
            ];

            $data = $relationMap[$typeClass] ?? null;

            if (!$data || !method_exists($modelsub, $data['relation'])) return;

            $relation = $data['relation'];
            $foreignKey = $data['foreign_key'];

            $exists = $modelsub->{$relation}()
                ->where($foreignKey, $typeId)
                ->exists();

            if ($exists) {
                $modelsub->{$relation}()->detach($typeId);
            } else {
                $modelsub->{$relation}()->attach($typeId);
            }

            // بروزرسانی کیبورد
            $this->sendClassificationSuggestion($chatId, $modelsub, $messageId,$filterModelTypes);
            return;
        }

        // مدیریت صفحه‌بندی نامه یا کار
        if (str_contains($callbackData, '_page_')) {
            // مثال: letter_page_2|جستجو یا task_page_3|کلمه
            [$prefix, $rest] = explode('_page_', $callbackData, 2);
            [$page, $queryText] = explode('|', $rest . '|'); // اگر queryText خالی بود، رشته دوم خالی می‌ماند

            $page = (int) $page;
            $queryText = trim($queryText);

            if ($prefix === 'نامه') {
                $query = Letter::query()->orderByDesc('id');
                // اگر queryText وجود دارد، فیلتر اعمال کن
                if (is_numeric($queryText)) {
                    $query->where('id', $queryText);
                } elseif ($queryText !== '') {
                    $queryTextPersent = str_replace(' ', '%', $queryText);
                    $query->where('subject', 'like', "%{$queryTextPersent}%");
                }
                $this->paginateAndSend($chatId, $query, $queryText, $page, 5, 'نامه', $user,$messageId);
            }

            if ($prefix === 'کار') {
                $query = Task::query()->orderByDesc('id');
                if (is_numeric($queryText)) {
                    $query->where('id', $queryText);
                } elseif ($queryText !== '') {
                    $query->where('name', 'like', "%{$queryText}%");
                }
                $this->paginateAndSend($chatId, $query, $queryText, $page, 5, 'کار', $user,$messageId);
            }

            if ($prefix === 'صورتجلسه') {
                $query = Minutes::query()->orderByDesc('id');
                if (is_numeric($queryText)) {
                    $query->where('id', $queryText);
                } elseif ($queryText !== '') {
                    $query->where('title', 'like', "%{$queryText}%");
                }
                $this->paginateAndSend($chatId, $query, $queryText, $page, 5, 'صورتجلسه', $user,$messageId);
            }
        }
    }

    public function sendClassificationSuggestion($chatId, Model $modelsub, $messageId = null,?array $filterModelTypes = null)
    {
        $title = $modelsub->name ?? $modelsub->subject ?? $modelsub->title ?? '-';

        $classifier = app(\App\Services\AiKeywordClassifier::class);
        $results = $classifier->classify($title, 0.1, $filterModelTypes, null, 3);

        // نگاشت مدل به: رابطه، کلید، و عنوان
        $relationMap = [
            \App\Models\Project::class => [
                'relation' => 'project',
                'foreign_key' => 'project_id',
                'label' => 'دستور کار ها',
            ],
            \App\Models\TaskGroup::class => [
                'relation' => 'group',
                'foreign_key' => 'task_group_id',
                'label' => 'دسته بندی ها',
            ],
        ];

        $keyboard = ['inline_keyboard' => []];

        foreach ($results as $modelType => $group) {
            $data = $relationMap[$modelType] ?? null;

            if (!$data || !method_exists($modelsub, $data['relation'])) {
                continue;
            }

            // عنوان سربرگ غیرقابل کلیک
            $keyboard['inline_keyboard'][] = [
                ['text' => '--- '.'📂 ' . $data['label'] .' ---', 'callback_data' => '__noop__']
            ];

            foreach ($group as $item) {
                $modelId = $item['model_id'];
                $model = $modelType::find($modelId);
                $modelTitle = $model?->title ?? $model?->name ?? '---';

                $isSelected = $modelsub->{$data['relation']}()
                    ->where($data['foreign_key'], $modelId)
                    ->exists();

                $text = ($isSelected ? '✅ ' : '') . $modelId . '-' . $modelTitle;
                $callback_data = "toggle_category|{$modelsub->getMorphClass()}|{$modelsub->id}|{$modelType}|{$modelId}|" . json_encode($filterModelTypes);

                $keyboard['inline_keyboard'][][] = [
                    'text' => $text,
                    'callback_data' => $callback_data,
                ];
            }
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => '❌ حذف پیام', 'callback_data' => 'delete_message']
        ];

        $textMessage = "📌 پیشنهادهایی برای دسته‌بندی:" . "\n" . "(لطفا موارد مناسب را انتخاب کنید)";
        if (is_null($messageId)) {
            $this->sendMessageWithKeyboard($chatId, $textMessage, $keyboard);
        } else {
            $token = env('BALE_BOT_TOKEN');
            Http::post("https://tapi.bale.ai/bot{$token}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $textMessage,
                'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private function paginateAndSend($chatId, $query, $queryText, $page, $perPage, $type, $user,$messageId = null)
    {
        $totalCount = $query->count();
        $totalPages = ceil($totalCount / $perPage);
        $items = $query->forPage($page, $perPage)->get();

        if ($items->isEmpty()) {
            $this->sendMessage($chatId, "📭 هیچ {$type}ی مطابق با جستجوی شما یافت نشد.");
            return;
        }

        $paginateMessage = " صفحه {$page} از {$totalPages}";
        $message = $queryText
            ? "🔍 نتیجه جستجو برای «{$queryText}» - {$paginateMessage}:\n\n"
            : "🗂 لیست {$type}های شما - {$paginateMessage}:\n\n";

        foreach ($items as $item) {
            if ($type === 'نامه') {
                $message .= $this->CreateLetterMessage($item);
                $message .= '[بازکردن در سامانه](' . LetterResource::getUrl('edit', [$item->id]) . ")\n";
            } elseif($type === 'کار') {
                $message .= $this->CreateTaskMessage($item, $user);
                $message .= '[بازکردن در سامانه](' . TaskResource::getUrl('edit', [$item->id]) . ")\n";
            }elseif($type === 'صورتجلسه') {
                $message .= $this->createMinuteMessage($item, $user, false);
                $message .= '[بازکردن در سامانه]('. MinutesResource::getUrl('edit',[$item->id]).')' . "\n";
            }
            $message .= "----------------------\n";
        }

        $message .= "\n" . $paginateMessage;

        // ساخت کیبورد
        $keyboard = ['inline_keyboard' => []];
        $buttons = [];
        if ($page < $totalPages) {
            $buttons[] = ['text' => '➡️ بعدی', 'callback_data' => "{$type}_page_" . ($page + 1) . "|{$queryText}"];
        }
        if ($page > 1) {
            $buttons[] = ['text' => 'قبلی ⬅️', 'callback_data' => "{$type}_page_" . ($page - 1) . "|{$queryText}"];
        }
        if (!empty($buttons)) {
            $keyboard['inline_keyboard'][] = $buttons;
        }
        $keyboard['inline_keyboard'][] = [['text' => '❌ حذف پیام', 'callback_data' => 'delete_message']];

        if (is_null($messageId)){
            $this->sendMessageWithKeyboard($chatId, $message, $keyboard);
        }else{
            $token = env('BALE_BOT_TOKEN');
            // ویرایش همان پیام قبلی
            Http::post("https://tapi.bale.ai/bot{$token}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }


    public function HelpHandler(string $queryText): string
    {
        $message = '';
        if ($queryText != ''){
            $queryText = str_replace(['#','/'],'',$queryText);
            if ($queryText == 'کار'){
                $message = <<<TEXT
راهنمای کار

ℹ️ تعریف کار :  کار به معنای هر چیز انجام شدنی مانند مصوبات ، پیگیری ها ، انواع جلسات ، دیدار ها ، نشست ها ، بازدید ها و ... می باشد .

✅ #کار
ساختار آن باید به شکل زیر باشد .
------------------------------
#کار عنوان جلسه یا کار ، تاریخ عددی
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

☑️ افزودن ضمیمه
برای ضمیمه کردن یک فایل در کار می توانید متن ساختارمند برای ایجاد کار را در کپشن فایل مورد نظر نوشت یا همان متن را در پاسخ (ریپلای) یه فایل ارسال کرد.

☑️ مشخص کردن دستورکار مربوطه
برای این کار میتوان دستور کار مربوط به کار را مشخص کرد اگر در آخر عنوان یا خط های بعدی از کلمات کلیدی دستورکار یا پروژه و در ادامه آن شماره یا عنوان دستور کار ها استفاده کرد .مانند مثال زیر که دو مورد دستور کار است :
#کار عنوان دستورکار ۱۵۶۷،۴۴۳۳
یا
#کار عنوان
دستورکار احداث زمین چمن، خانه ملت
یا
#کار عنوان پروژه ۱۵۶۷،۴۴۳۳
نکته : کلمه کلیدی و عناوین یا شماره های دستور کار در یک خط باید باشند .

✅ /کار
این دستور ۵ کار آخر مربوط به کاربر احراز هویت شده را ارسال می کند .
☑️ جستجو در کار ها و یا نمایش یک کار
می‌ توان بایه فاصله بعد از دستور عنوان کار یا شماره ثبت کار را وارد کرد تا با استفاده از آن در صورت جلسه ها جستجو شود . مانند مثال زیر :
/کار عنوان کار مورد نظر
یا
/کار 57487

- جستجو در کار ها بر اساس صورتجلسه ها
اگر در ابتدای خط دوم کلمه صورتجلسه را بنویسید و بعد از آن شماره ثبت صورتجلسه مورد نظر ، در کار های مربوط به آن صورت جلسه جستجو می شود و کار های مربوطه نمایش داده می شود . مانند مثال زیر
/کار 15478
صورتجلسه 4574

☑️ تغییر وضعیت کار به انجام شده
اگر بعد از عنوان یا شماره ثبت که برای جستجو استفاده می شود از #انجام استفاده نمایید وضعیت آن کار به انجام شده تغییر می باید . مانند مثال زیر :
/کار 54545 #انجام
یا
/کار عنوان کار #انجام

☑️ توضیحات کار
برای افزودن توضیحات به یک کار می توان توضیحات را در خط های غیر از عنوان به تعداد خط دلخواه نوشت مانند مثال زیر :
/کار 5454545
خط اول توضیحات کار
خط دوم برای توضیح کار
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

 @نام ارگان اول @ نام ارگان چندم

------------------------------

ℹ️ تعاریف هر بخش :

☑️ عنوان :
باید در اولین خط کپشن فایل صورت جلسه آورده شود با #صورتجلسه
عنوان باید با عنوان جلسه ای که در ایتا بارگزاری شده است شباهت داشته باشد . (از نظر لغوی ، نه مفهومی )
 تاریخ باید در عنوان باشد اگر تاریخ نباشد در عنوان ، تاریخ جلسه ثبت شده ، یا تاریخ روز در نظر گرفته می شود .
تاریخ ها باید به صورت عددی نوشته شود به عنوان نمونه ۱۴۰۴/۵/۶
📎 نکته :
باید خبر جلسه قبل از بارگزاری صورت جلسه در کانال ایتای جناب آقای حاجی بارگزاری شده باشد تا صورت جلسه زمیمه آن جلسه شود . اگر جلسه توی کانال قرار نیست بارگزاری شود به هر دلیلی می توان از ساختار زیر برای ایجاد جلسه متناسب با صورتجلسه استفاده کرد .
می توان در یک خط جدید (هر خط از متن غیر از خط اول ) در ابتدای خط از کلمه جلسه یا ایجاد جلسه استفاده کرد . در ادامه خط می توان عنوان جلسه را وارد کرد . اگر عنوانی وارد نکنید عنوان صورت جلسه برای جلسه در نطر گرفته می شود مانند نمونه های زیر :
ایجاد جلسه
یا
جلسه با انجمن صنفی ...
☑️ مصوبات :
در خط های بعدی آورده شود و هر مصوبه در یک خط جدا گانه که با ( - ) (خط تیره) یا ( _ ) (زیر خط ) شروع شود .
توی هر خط می توان از یک @ برای تایین مسئول پیگیری اون کار در نطر گرفت مثل @خیری یا @قدسیه یا @طلبی یا @طالبی و... ( باید توجه داشت که در مصوباتی که دستگاه اجرایی مسئول انجام آن است @ به معنای فردی است که باید پیگیری کند آن کار توسط ارگان مربوطه انجام شده است یا خیر ) اگر تعریف نشود شخصی که صورت جلسه را فرستاده به عنوان پیگیری کننده در نظر می گیرد.
اگر اسم ارگانی که مسئول انجام آن مصوبه است در متن باشد تشخیص داده می شود و ثبت می شود. اگر نباشد ارگانی در نظر گرفته نمی‌شود.
اگر نیاز به ثبت اعتبار اخذ شده برای یک مصوبه هست میتوان ادامه متن مصوبه از $ عدد استفاده کرد. مانند مثال زیر :
-متن مصوبه اول $ 36,000,000,000
نکته : اعتبار وارد شده باید به ریال باشد . این عدد از عنوان مصوبه حذف می شود .
☑️ دستورکار :
میتوان دستور کار مربوط به مصوبه را مشخص کرد اگر در ادامه متن مصوبه از کلمات کلیدی دستورکار یا پروژه و در ادامه آن شماره یا عنوان دستور کار ها مانند مثال زیر که یک مصوبه برای دو مورد دستور کار است :
- متن مصوبه دستورکار ۱۲۵،انتشار نشریه
- متن مصوبه پروژه بازدید ها
نکته : اگر این مصوبه برای چند دستورکار هست می توان بین شماره دستورکار و نام دستور کار ها از کاراکتر ، یا . استفاده کرد .
همچنین می توان برای همه مصوبات دستورکار کلی مشخص کرد که همه مصوبات ذیل آن دستور کار قرار گیرند ، به این صورت که در خطی غیر از عنوان یا خط مربوط به مصوبه ها ازکلمات دستور کار یا پروژه استفاده می کنیم. مانند نمونه :
#صورتجلسه عنوان
 *دستورکار 12،تعریض جاده ، 154*
-مصوبه اول

نکته : اگر برای هر مصوبه دستور کاری به نحوی که در قبل توضیح داده شد تعریف شود ، به جای دستورکار کلی در نظر گرفته می شود .
☑️ امضا ها :
باید اسم ارگان مربوطه با @ در انتهای توضیحات اضافه گردد . به عنوان مثال اگر بخواهیم دو امضا کننده راه و شهر سازی استان و اداره راه داری استان را داشته باشم باید به صورت زیر اضافه کنیم :
@راه و شهرسازی استان @راهداری استان
یا
@ راهداری استان @ سازمان صنعت

نکته : نیاز نیست از @نماینده یا @حاجی در قسمت امضاکنندگان استفاده شود ؛ زیرا به طور پیش فرض این صورت جلسه ها با امضا و تایید آقای حاجی نماینده محترم اعتبار و ارزش ثبت دارد .

✅ /صورتجلسه
این دستور ۵ صورت جلسه آخر مربوط به کاربر احراز هویت شده را ارسال می کند .
می‌ توان بایه فاصله بعد از دستور عنوان صورتجلسه یا شماره صورتجلسه را وارد کرد تا با استفاده از آن در صورت جلسه ها جستجو شود .
✅ #مصوبه
گاهی اوقات مصوبات طولانی هستند و در کپشن یک تصویر جا نمی‌شوند یا می خواهید تعدادی از مصوبات صورت جلسه را بعدا وارد کنید ، در این صورت می توانید از #مصوبه اسفاده کنید .
این هشتگ را باید در ابتدای یک پیام به کار ببرید و بعد از آن حتما باید شماره ثبت صورتجلسه مورد نظر یاداشت شود ؛ در خط های بعدی طبق ساختار تعریف شده برای مصوبه ها در صورت جلسه ها ، مصوباتی که لازم است به یک صورتجلسه از قبل ثبت شده اضافه شود را وارد کنید . به عنوان مثال :
#مصوبه ۱۳۷۳
- مصوبه اول
-مصوبه
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
نکته : می‌توان نام دفتر را ننوشت در آن صورت دفتر تهران به طور پیش فرض دفتر تهران در نظر گرفته می شود .

☑️ @
اگر نیاز هست نامه به کارتابل اشخاصی اضافه گردد کافی است درخط های بعدی کپشن از @نام استفاده کرد . به عنوان مثال
@طالبی @نظری
نکته : تنها یک کلمه از فامیلی یا سمت شخص کافی است ، مابقی اسم را ربات توی ثبت نمامه می نویسد . اگر تشابه فامیلی ها وجود دارد بهتر است یک کلمه از سمت شخص مورد نظر را بنویسید مانند @رئیس یا @مدیر

☑️ مکاتبه
این کلمه را هر کجای متن زیر عکس بنویسید (ترجیحا در خط بعدی از عنوان ) در ادامه آن میتوانید شماره مکاتبه فیزیکی را وارد نمایید .
مانند :
مکاتبه 110/45 یا مکاتبه 1404-12
نکته : اگر شماره مکاتبه فیزیکی را بعد از # نامه وارد کردید دیگر نیاز نیست دوباره از کلمه مکاتبه برای افزودن شماره مکاتبه فیزیکی استفاده کنید . به عبارت دیگر استفاده از یکی از حالات کافی است .

☑️ پیرو
این کلمه را هر کجای متن زیر عکس بنویسید (ترجیحا در خط بعدی از عنوان ) در ادامه آن میتوانید مشخص کنید این نامه پیرو کدام نامه است ، شماره ثبت نامه در سامانه را باید بعد از این کلمه بیارید . اگر نیاز هست نامه ای که میخواهید برای آن پیرو بزنید بر اساس شماره مکاتبه فیزیکی پیدا شود ، کافی است بعد از کلمه پیرو ، کلمه مکاتبه و بعد از آن شماره مکاتبه فیزیکی نامه مورد نظر.
مانند :
پیرو 1345
پیرو مکاتبه 1404/15

☑️ = یا کلمات : صاحب ، شخص
با استفاده از کاراتر مساوی یا کلمه صاحب در ابتدای خط های بعدی می‌توان صاحب نامه را مشخص کرد . تعداد صاحب های نامه محدودیت تعداد ندارد و به تعداد آن ها می توان در خط های مجازا = گذاشت .
اگر صاحب یه ارگان است بعد از مساوی یا کلمه صاحب نام آن ارگان را نوشت و اگر یه صاحب یه شخص حقیق است باید بعد از مساوی یا کلمه صاحب کلمه شخص و کد ملی  نام و نام خانوادگی آورده شود . مانند مثال زیر
=شخص 5100248724 محمدمهدی حق شناس
یا
صاحب شخص 5100248629 محمد مهدی حق شناس

نکته : اگر می دانید که اطلاعات شخص قبلا ثبت شده است در سامانه نوشتن کد ملی کافی است .

☑️ + یا کلمات : هامش ، خلاصه ‌، نتیجه ، پاراف
کاراکتر و کلمات تعریف شده باید ابتدای خط بیاید و بعد از آن هامش نامه را نوشت.

☑️ - یا کلمات : توضیح ، متن ‌، توضیحات
کاراکتر یا کلمات تعریف شده باید ابتدای خط بیاید و بعد از آن توضیحات یا متن نامه را نوشت.

 ☑️ دستورکار یا پروژه
کلمات تعریف شده باید ابتدای خط بیاید و بعد از آن شماره دستورکار یا عنوان دستورکار را باید نوشت تا دستور کار نامه ثبت گردد . اگر یک نامه زیر مجموعه چند دستور کار است می توان شماره یا عنوان هر کردام را با ، یا . از یکدیگر جدا باید کرد .
مثال :
دستورکار ۱۲۴
یا
پروژه انتشار نشریه
یا
دستورکار ۱۲۸۹ ،۱۲۴،۴۵۵
یا
پروژه ۱۲۳، انتشار نشریه

☑️ #اتمام یا #انجام یا #شد یا #انجام_شد
این هشتک اگر در متن باشد وضعیت نامه در حالت اتمام قرار می گیرد در غیر این صورت وضعیت در حالت درحال پیگیری قرار می گیرد.

✅ /نامه
این دستور ۵ نامه آخر مربوط به کاربر احراز هویت شده را ارسال می کند .
می‌ توان بایه فاصله بعد از دستور عنوان نامه یا شماره ثبت نامه را وارد کرد تا با استفاده از آن در نامه ها جستجو شود .
TEXT;
            }
        }else{
            $message = <<<TEXT
ℹ️ راهنمای ربات

دستوراتی که با / شروع می‌شوند برای اطلاعات موجود در سامانه هستند و دستوراتی که با # شروع می شوند برای ثبت اطلاعات جدید در سامانه هستند .

✅ لیست دستورات ثبتی :
#صورتجلسه
ایجاد صورت جلسه
#کار یا #جلسه
ایجاد کار شامل جلسه ، مصوبه ، پیگیری ، بازدید و ...
#نامه
ایجاد نامه

✅ لیست دستورات نمایش اطلاعات :
/راهنما
این دستور راهنمای ربات را ارسال می کند . در ادامه دستور عنوان هر موجودیت شامل کار ، نامه ، صورتجلسه و... را وارد کنید راهنمای آن موجودیت را برای شما ارسال می شود.
/صورتجلسه
نمایش 5 صورت جلسه آخر شما . بعد از دستور میتوان شماره صورتجلسه یا عنوان صورتجلسه را برای جستجو در صورتجلسه ها استفاده کرد.
/کار
نمایش 5 کار آخر شما . بعد از دستور میتوان شماره ثبت کار یا عنوان کار را برای جستجو در کار ها استفاده کرد.
/نامه
نمایش 5 نامه آخر شما . بعد از دستور میتوان شماره نامه یا عنوان نامه را برای جستجو در نامه ها استفاده کرد.
/ارجاع
لیست نامه هایی که به شما ارجاع شده است را ارسال می کند . بعد از دستور میتوان شماره نامه یا عنوان نامه را برای جستجو در نامه ها استفاده کرد .
/کارپوشه
لیست نامه های بررسی نشده در کارپوشه شما را ارسال می کند . اگر بعد از دستور #همه استفاده شود نامه های بررسی شده هم ارسال می شوند . بعد از دستور میتوان شماره نامه یا عنوان نامه را برای جستجو در نامه ها استفاده کرد.
/دستورکار
نمایش لیست ۵ تا از دستورکار های مربوط به شما . بعد از دستور میتوان شماره دستور کار یا کلماتی برای جستجو وارد کرد .
/ارگان
نمایش ارگان های تعریف شده در سیستم.

⚠️ توجه !
ربات به فاصله ها (اسپیس یا فضای خالی) بین کلمات و دستورات حساس می باشد.  به عنوان مثال ( # صورت جلسه ) یا (/ کار ) اشتباه است و شکل صحیح آن ( #صورتجلسه ) یا (/کار) می باشد .
TEXT;

        }
        return $message;
    }

    private function handleTasks_create($text,$user,$chatId,$isPrivateChat, array $group = null)
    {
        // استخراج دستور کار
        $extractedProjects = $this->extractProjects($text);
        $text = $extractedProjects['text'];
        $projects_id = $extractedProjects['projects_id'];

        $lines = explode("\n", $text);
        $firstLine = $lines[0] ?? '';
        // حذف #کار از ابتدای متن و تمیز کردن فاصله‌ها
        if (str_starts_with($firstLine, '#کار')){
            $title = trim(substr($firstLine, strlen('#کار')));
        } else{
            $title = trim(str_replace('#', '', $firstLine));
        }

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
                'created_by' => $user->id,
                'city_id' => $cats['city'],
                'organ_id' => $cats['organ'],
            ];
            $task = Task::create($dataTask);
            $task->project()->attach(count($projects_id) != 0 ? array_unique($projects_id) : $cats['categories']);
            $task->group()->attach([32]);
            if ($group){
                $task->group()->attach($group);
            }else{
                $task->group()->attach([($user->id == 20) ? 1 : 2]);
            }

            $message = '';
            if ($isPrivateChat){
                $message .= '🕹️ کار با مشخصات زیر ثبت شد :' . "\n";
                $message .= $this->CreateTaskMessage($task,$user);
                $message .= "\n" . '[بازکردن در سامانه](' . TaskResource::getUrl('edit', [$task->id]) . ')';
            }else{
                $message .= '🕹️ [کار با شماره '.$task->id.' ثبت شد .]('. TaskResource::getUrl('edit',[$task->id]).')';
            }
            $this->sendMessage($chatId, $message);

            if (count($projects_id) == 0){
                $this->sendClassificationSuggestion($chatId, $task);
            }

            return $task;
        }
        return null;
    }

    private function handleMinute_create($caption,$chatId,$user,$isPrivateChat,$doc)
    {
        if (!$user->can('create_minutes')) {
            $this->sendMessage($chatId, '❌ شما برای ایجاد صورت‌جلسه‌ دسترسی ندارید!');
            return response('عدم دسترسی');
        }

        $pmID = $this->sendMessage($chatId, "📝🔄 در حال پردازش صورت جلسه" . "\n");
        $message = '';

        try {
            $mp = new \App\Http\Controllers\ai\MinutesParser();
            $parsedData = $mp->parse($caption, $user->id);

            $mdata = [
                'title' => $parsedData['title'],
                'date' => $parsedData['title_date'] ?? Carbon::now(),
                'text' => $caption,
                'typer_id' => $user->id,
                'task_id' => $parsedData['task_id'],
            ];

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
                    'amount' => $approve['amount'],
                    'ended_at' => $approve['due_at'] ?? null,
                    'Responsible_id' => $approve['user']['id'] ?? $user->id,
                    'created_by' => $user->id,
                    'minutes_id' => $record->id,
                    'city_id' => $cp->detectCity($keywords),
                    'organ_id' => $cp->detectOrgan($keywords),
                ]);
                $task->group()->attach([33, 32]); // دسته بندی هوش مصنوعی و مصوبه
                $task->project()->attach($approve['projects'] ?: $parsedData['global_projects']);
            }

            if ($isPrivateChat){
                $message .= '✅ صورت جلسه با مشخصات زیر ذخیره شد : ' . "\n\n";
                $message .= '[بازکردن در سامانه]('. MinutesResource::getUrl('edit',[$record->id]).')' . "\n\n";
                $message .= $this->createMinuteMessage($record, $user);
            }else{
                $message .= '📝 [صورتجلسه با شماره '.$record->id.' ثبت شد .]('. MinutesResource::getUrl('edit',[$record->id]).')';
            }
            $this->sendMessage($chatId, $message);

            if (empty($parsedData['approves']) and $doc) {
                $loadinApAi = $this->sendMessage($chatId,"درحال استخراج مصوبات با هوش مصنوعی");
                $ocrResponse = Http::asForm()->post('https://www.eboo.ir/api/ocr/getway', [
                    'token' => env('EBOO_OCR_TOKEN'),
                    'command' => 'addfile',
                    'filelink' => $this->getFileUrl($doc['file_id']),
                ]);

                $ocrdata = json_decode($ocrResponse->body());

                if (true or $ocrdata->Status == 'Done') {
                    $ocrResponse2 = Http::asForm()->post('https://www.eboo.ir/api/ocr/getway', [
                        'token' => env('EBOO_OCR_TOKEN'),
                        'command' => 'convert',
                        'output' => 'txtraw',
                        'filetoken' => $ocrdata->FileToken,
                        'method' => 4,
                    ]);
                    $ocrText = $ocrResponse2->body();
                    $jalaliD = Jalalian::fromDateTime($record->date)->format('Y/m/d');

                    // ارسال به GapGPT برای اصلاح و تبدیل به ساختار مصوبات
                    $aiResponse = Http::withHeaders([
                        'Authorization' => 'Bearer ' . env('GAPGPT_API_KEY'),
                    ])->post('https://api.gapgpt.app/v1/chat/completions', [
                        'model' => 'gpt-4o',
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => <<<EOT
متن زیر با OCR از یک صورتجلسه استخراج شده است. لطفاً آن را اصلاح کن و فقط مصوبات را به شکل زیر  بدون هیچ توضیح اظافه ای بازگردان:
هر مصوبه در یک خط جداگانه که با "-" شروع شود.
اگر اعتبار ریالی ذکر شد با $ عدد مشخص شود. مانند $100000 ریال .
متن و اطلاعات هر مصوبه در یک خط باشند.
لطفاً متن مصوبات زیر را بررسی کن و برای هر مصوبه‌ای که در آن مهلت یا مدت زمان مشخصی برای انجام کار ذکر شده است، مدت زمان را به صورت زیر بازنویسی کن:

- فرمت مدت زمان باید به شکل « تا X روز » یا « تا X ماه » یا « تا X سال آینده » باشد.
- محاسبه مدت زمان باید بر اساس تاریخ شروع {$jalaliD} انجام شود.
- اگر در متن مصوبه هیچ مهلت یا مدت زمانی ذکر نشده بود، هیچ عبارتی اضافه نکن و همان متن مصوبه را بدون تغییر برگردان.
- خروجی فقط متن اصلاح‌شده مصوبات باشد، بدون توضیحات اضافی یا متن اضافه.

مثال:
«مدت ۲۰ روز» → « تا 20 روز »
«این کار  تا پایان سال 1405 انجام شود» → « تا 2 ماه آینده » (مدت زمان باقی مانده از تاریخ شروع تا ابتدای سال ذکر شده )
«یک سال فرصت برای تکمیل وجود دارد» → « تا 1 سال آینده »

متن صورتجلسه :
{$ocrText}
EOT
                            ],
                        ],
                    ]);

                    $ocrApprovesText = $aiResponse->json('choices.0.message.content');
                    $ocrApprovesText = "\n\n" . "#مصوبه " . $record->id . "\n" . $ocrApprovesText;

                    $apm = "هوش مصنوعی مصوبات زیر را از متن صورتجلسه استخراج کرده است . لطفا متن مصوبات را اصلاح کنید و برای ربات بفرستید تا مصوبات ضمیمه صورتجلسه شوند :";
                    $apm .= $ocrApprovesText;
                    $keyboard['inline_keyboard'][] = [ ['text' => '📋 کپی متن مصوبات', 'copy_text' => $ocrApprovesText], ['text' => '❌ حذف پیام', 'callback_data' => 'delete_message'] ];
                    $this->sendMessageWithKeyboard($chatId,$apm,$keyboard);
                }

                $this->deleteMessage($chatId,$loadinApAi);

            }


        }catch (Exception $exception){
            $message = '❌ ثبت صورت جلسه با مشکل مواجه شد .';
            $this->sendMessage($chatId, $message);

            $content = $this->AiChat(<<<EOT
 اطلاعات زیر و خطای سیستم را بررسی کن و با توجه به این دو مورد پیشنهاد اصلاح پیام و توضیح خلاصه و مناسب برای کاربر بده و اصلا نیازی به توضیحات فنی نیست. توضیح اضافه ای نده .

اطلاعات :
{$this->HelpHandler('صورتجلسه')}

خطای سیستم :
{$exception->getMessage()}

پیام ارسال شده:
{$caption}

EOT);
            $ai_message =  "🤖 #پیشنهاد_اصلاح : \n\n". $content;
            $keyboard['inline_keyboard'][] = [['text' => '❌ حذف پیام', 'callback_data' => 'delete_message']];
            $this->sendMessageWithKeyboard($chatId,$ai_message,$keyboard);



            throw $exception;
        } finally {
            $this->deleteMessage($chatId,$pmID); //حذف پیام پردازش
        }

        return $record;
    }

    private function MinuteFileAdd($record,$doc,$media_group_id,$bale_user)
    {
        $record->update(['file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION)]);
        Storage::disk('private_appendix_other')->put($record->getFilePath(), $this->getFile($doc['file_id']));
        if ($media_group_id) {
            $state_data = explode('_', $bale_user->state);
//                            $this->sendMessage(1497344206,json_encode($state_data));
            if ($state_data[0] == "$media_group_id") {
                $child = $record::withoutEvents(function () use ($record, $state_data) {
                    return $record->appendix_others()->create([
                        'title' => 'ضمیمه',
                        'file' => $state_data[2],
                    ]);
                });

                Storage::disk('private_appendix_other')
                    ->put($child->getFilePath(), $this->getFile($state_data[1]));
            }
        }
        $bale_user->update(['state' => '1']);

        return true;
    }

    private function handleLetter_create($caption,$chatId,$user,$isPrivateChat)
    {
        if (!$user->can('create_letter')) {
            $this->sendMessage($chatId, '❌ شما برای ایجاد نامه دسترسی ندارید!');
            return response('عدم دسترسی');
        }

        $ltp = new LetterParser();
        $dataLetter = $ltp->parse($caption);

        $record = Letter::create([
            'subject' => $dataLetter['title'],
            'created_at' => $dataLetter['title_date'] ?? Carbon::now(),
            'description' => $dataLetter['description'],
            'summary' => $dataLetter['summary'],
            'mokatebe' => $dataLetter['mokatebe'],
            'daftar_id' => $dataLetter['daftar'],
            'kind' => $dataLetter['kind'],
            'user_id' => $user->id,
            'peiroow_letter_id' => $dataLetter['pirow'],
            'status' => $dataLetter['status'],
        ]);

        if ($dataLetter['kind'] == 1) {
            $record->organ_id = $dataLetter['organ_id'];
            $record->save();
        } else {
            $record->organs_owner()->attach($dataLetter['organ_id']);
        }

        $record->users()->attach($dataLetter['user_id']); //افزودن به کارپوشه
        $record->organs_owner()->attach($dataLetter['organ_owners']);
        $record->customers()->attach($dataLetter['customer_owners']);
        $record->projects()->attach($dataLetter['projects']);

        $message = '';
        if ($isPrivateChat){
            $message .= '✉️ اطلاعات نامه ذخیره شد' . "\n\n";
            $message .= '[بازکردن در سامانه](' . LetterResource::getUrl('edit', [$record->id]) . ')' . "\n\n";
            $message .= $this->CreateLetterMessage($record);
        }else{
            $message .= '✉️ [نامه با شماره '.$record->id.' ثبت شد .]('. LetterResource::getUrl('edit',[$record->id]).')';
        }

        $this->sendMessage($chatId, $message);

        if (count($dataLetter['projects']) == 0){
            $this->sendClassificationSuggestion($chatId, $record,filterModelTypes: [\App\Models\Project::class]);
        }

        return $record;
    }

    private function LetterFileAdd($record,$doc,$media_group_id,$bale_user)
    {
        $record->update(['file' => pathinfo($doc['file_name'], PATHINFO_EXTENSION)]);
        Storage::disk('private')->put($record->getFilePath(), $this->getFile($doc['file_id']));
        if ($media_group_id) {
            $bale_user->update(['state' => $media_group_id . "_letter_{$record->id}"]);
        }
    }

    private function extractProjects($text)
    {
        $projects_id = [];

        // همه تطابق‌ها را پیدا کن (نه فقط اولین مورد)
        if (preg_match_all('/(?:پروژه|دستور\s*کار?)\s*(.+)/u', $text, $matches)) {
            foreach ($matches[1] as $content) {
                $items = preg_split('/[،\.]+/u', trim($content));

                foreach ($items as $item) {
                    $item = trim($item);
                    if (!$item) continue;

                    if (is_numeric($item)) {
                        $project = Project::find($item);
                        if ($project) {
                            $projects_id[] = $project->id;
                        }
                    } else {
                        $project = Project::query()
                            ->where('name', 'like', '%' . $item . '%')
                            ->first();
                        if ($project) {
                            $projects_id[] = $project->id;
                        }
                    }
                }
            }

            // پاک کردن همه موارد پروژه/دستور کار از متن
            $text = preg_replace('/(?:پروژه|دستور\s*کار?)\s*(.+)/u', '', $text);
        }

        return [
            'text' => $text,
            'projects_id' => array_unique($projects_id),
        ];
    }


    private function authBale(array $userMessage,$chatId,string $text = null) : array|string
    {
        try {
            $bale_user = BaleUser::query()->where('bale_id', $userMessage['id'])->first();
        if ($bale_user == null and $text) {
            // بررسی کد اهراز هویت
            $bale_user_auth = BaleUser::query()->where('bale_username', $text)->first();
            if ($bale_user_auth != null) {
                $bale_user_auth->update([
                    'state' => '1',
                    'bale_username' => $userMessage['username'] ?? null,
                    'bale_id' => $userMessage['id'],
                ]);
                $this->sendMessageWithReplyKeyboard($chatId, "✅ شما با موفقیت احراز هویت شدید !" . "\n" . "با ارسال دستور /راهنما می توانید لیست دستورات کار با ربات را دریافت نمایید .");
                return 'احراز شده';
            }
            if (isset($data['message']['chat']['type']) and $data['message']['chat']['type'] == "private") $this->sendMessage($chatId, "❌ شما احراز هویت نشده اید . \n  کد را از سامانه دریافت کن و برای من بفرست .");
            return 'احراز نشده';
        }
        $user = \App\Models\User::query()->find($bale_user->user_id);
        Auth::guard('bot')->login($user);
        config(['activitylog.default_auth_driver' => 'bot']);

        return [$user,$bale_user];
        } catch (Exception $exception) {
            return 'احراز نا معتبر';
        }
    }
}
