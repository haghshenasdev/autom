<?php

namespace App\Http\Controllers;

use App\Filament\Resources\LetterResource;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\TaskResource;
use App\Filament\Resources\ContentResource;
use App\Http\Controllers\ai\CategoryPredictor;
use App\Http\Controllers\ai\LetterParser;
use App\Models\AppendixOther;
use App\Models\Cartable;
use App\Models\Content;
use App\Models\ContentGroup;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Minutes;
use App\Models\MinutesGroup;
use App\Models\Letter;
use App\Models\Task;
use App\Models\BaleUser;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;
use PhpMyAdmin\Url;

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
                elseif (str_starts_with($firstLine, '#نامه') || str_starts_with($firstLine, '#مکاتبه')) {
                    if (str_contains($text, '#متن')) {
                        $ltp = new LetterParser();
                        $dataLetter = $ltp->mixedParse($text);
                        $this->sendMessage($chatId, 'متن زیر را اصلاح کنید و زیر یک تصویر ارسال نمایید .');
                        $this->sendMessage($chatId, $ltp->rebuildText($dataLetter));
                    }elseif (isset($data['message']['reply_to_message']['document']['file_id'])) {
                        $reply_msg = $data['message']['reply_to_message'];
                        $doc = $this->getBaleAttachmentFromMessage($reply_msg);
                        $record = $this->handleLetter_create($text, $chatId, $user, $isPrivateChat, $doc);

                        if ($record instanceof Letter && $doc) {
                            $this->LetterFileAdd($record, $doc, $media_group_id, $bale_user);
                        }
                    }
                }
                elseif (str_starts_with($firstLine, '#یادداشت')) {
                    if (!$user->can('create_content')) {
                        $this->sendMessage($chatId, '❌ شما برای ثبت یادداشت دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }

                    $replyMessage = $data['message']['reply_to_message'] ?? [];
                    $doc = $this->getBaleAttachmentFromMessage($replyMessage);
                    $record = $this->handleContent_create($text, $chatId, $user, $doc);
                    if ($record instanceof Content) {
                        $this->sendContentGroupSuggestions($chatId, $record);
                    }
                    return response($record instanceof Content ? 'یادداشت ثبت شد.' : 'ثبت یادداشت ناموفق بود.');
                }
                                elseif (str_starts_with($firstLine,'#صورتجلسه')) {
                    $reply_msg = $data['message']['reply_to_message'] ?? [];
                    $doc = $this->getBaleAttachmentFromMessage($reply_msg);
                    if ($doc) {
                        $record = $this->handleMinute_create($text, $chatId, $user, $isPrivateChat, $doc);

                        // Only attach files to a successfully persisted minutes record.
                        if ($record instanceof Minutes) {
                            $saved = $this->MinuteFileAdd($record, $doc, $media_group_id, $bale_user, $chatId);
                            if ($saved) {
                                $this->sendMessage($chatId, '✅ فایل صورت‌جلسه با موفقیت ثبت و ذخیره شد.');
                            }
                            $this->sendClassificationSuggestion(
                                $chatId,
                                $record,
                                filterModelTypes: [Project::class, MinutesGroup::class]
                            );
                        }
                    } else {
                        $this->sendMessage($chatId,'لطفا این متن را در پاسخ یک فایل یا تصویر بفرست تا فایل صورتجلسه را در سامانه ثبت کنم');
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
                elseif (str_starts_with($firstLine, '/یادداشت')) {
                    $queryText = trim(preg_replace('/^\/یادداشت/u', '', $firstLine));
                    $this->handleContent_list($chatId, $user, $queryText);
                    return response('فهرست یادداشت‌ها ارسال شد.');
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
                $hashtags = ['#یادداشت', '#صورتجلسه', '#صورت', '#صورت-جلسه', '#نامه', '#مکاتبه', '#کار', '#جلسه', '#گزارش'];
                $matched = collect($hashtags)->filter(fn($tag) => str_contains($caption, $tag))->first();


                // ذخیره در مدل مناسب
                $record = null;
                if ($matched === '#یادداشت') {
                    if (!$user->can('create_content')) {
                        $this->sendMessage($chatId, '❌ شما برای ثبت یادداشت دسترسی ندارید!');
                        return response('عدم دسترسی');
                    }
                    $doc = $this->getBaleAttachmentFromMessage($data['message']);
                    $record = $this->handleContent_create($caption, $chatId, $user, $doc);
                    if ($record instanceof Content) {
                        $this->sendContentGroupSuggestions($chatId, $record);
                    }
                    return response($record instanceof Content ? 'یادداشت ثبت شد.' : 'ثبت یادداشت ناموفق بود.');
                } elseif (in_array($matched, ['#صورتجلسه', '#صورت', '#صورت-جلسه'])) {
                    // Bale sends ordinary images under `photo`, not `document`.
                    $doc = $this->getBaleAttachmentFromMessage($data['message']);
                    $record = $this->handleMinute_create($caption, $chatId, $user, $isPrivateChat, $doc);

                    if ($record instanceof Minutes) {
                        if ($doc) {
                            $saved = $this->MinuteFileAdd($record, $doc, $media_group_id, $bale_user, $chatId);
                            if ($saved) {
                                $this->sendMessage($chatId, '✅ صورت‌جلسه و فایل پیوست با موفقیت ثبت شدند.');
                            }
                        } else {
                            $this->sendMessage($chatId, '✅ صورت‌جلسه با موفقیت ثبت شد.');
                        }

                        $this->sendClassificationSuggestion(
                            $chatId,
                            $record,
                            filterModelTypes: [Project::class, MinutesGroup::class]
                        );
                    }
                    return response($record instanceof Minutes ? 'صورت جلسه ثبت شد.' : 'ثبت صورت جلسه ناموفق بود.');

                }
                elseif (in_array($matched, ['#نامه', '#مکاتبه'], true)) {
                    if (str_contains($caption, '#متن')) {
                        $ltp = new LetterParser();
                        $dataLetter = $ltp->mixedParse($caption);
                        $this->sendMessage($chatId, 'متن زیر را اصلاح کنید و زیر یک تصویر ارسال نمایید .');
                        $this->sendMessage($chatId, $ltp->rebuildText($dataLetter));
                    } else {
                        $doc = $this->getBaleAttachmentFromMessage($data['message']);
                        $record = $this->handleLetter_create($caption, $chatId, $user, $isPrivateChat, $doc);

                        if ($record instanceof Letter && $doc) {
                            $this->LetterFileAdd($record, $doc, $media_group_id, $bale_user);
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
        $token = config('bale.token');

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if (empty($token)) {
            Log::error('Bale sendMessage skipped: BALE_BOT_TOKEN is not configured.');
            return null;
        }

        try {
            // Retry transient connection failures (seen in logs as cURL error 7).
            $response = Http::connectTimeout(8)->timeout(20)->retry(2, 500)
                ->post("https://tapi.bale.ai/bot{$token}/sendMessage", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['result']['message_id'] ?? null;
                if ($messageId !== null) {
                    return (string) $messageId;
                }
            }

            Log::warning('Bale sendMessage API returned an unsuccessful response.', [
                'chat_id' => $chatId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Bale sendMessage request failed.', [
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);
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
    public function deleteMessage($chatId, $messageId): void
    {
        $token = config('bale.token');
        if (empty($token) || empty($chatId) || empty($messageId)) {
            return;
        }

        try {
            Http::connectTimeout(5)->timeout(10)->retry(1, 300)
                ->post("https://tapi.bale.ai/bot{$token}/deleteMessage", [
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
        } catch (\Throwable $exception) {
            // Cleanup is best-effort and must never break record/file creation.
            Log::warning('Bale deleteMessage request failed.', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function sendDocumentByUrl($chatId, $fileUrl, $caption = null): void
    {
        $token = config('bale.token');

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
        $token = config('bale.token');

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
        $token = config('bale.token');

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
        $token = config('bale.token');
        if (empty($token) || empty($filePath)) {
            throw new \RuntimeException('تنظیمات ربات بله یا شناسه فایل معتبر نیست.');
        }

        $response = Http::connectTimeout(10)->timeout(60)->retry(2, 500)
            ->get($this->getFileUrl($filePath));

        if (!$response->successful() || $response->body() === '') {
            throw new \RuntimeException('دریافت فایل از API بله ناموفق بود (HTTP ' . $response->status() . ').');
        }

        return $response->body();
    }

    private function getFileUrl($filePath): string
    {
        $token = config('bale.token');

        return "https://tapi.bale.ai/file/bot{$token}/{$filePath}";
    }

    private function sendMessageWithKeyboard($chatId, $text, $keyboard): void
    {
        $token = config('bale.token');
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

        // انتخاب نوع نامه
        if (str_starts_with($callbackData, 'letter_kind|')) {
            $parts = explode('|', $callbackData);
            $letter = Letter::find((int) ($parts[1] ?? 0));
            $kind = (int) ($parts[2] ?? 1);
            if ($letter && $user->can('create_letter') && in_array($kind, [0, 1], true)) {
                if ($kind === 0 && $letter->organ_id) {
                    $letter->organs_owner()->syncWithoutDetaching([$letter->organ_id]);
                    $letter->update(['kind' => 0, 'organ_id' => null]);
                } elseif ($kind === 1) {
                    $firstOwner = $letter->organs_owner()->first();
                    $letter->update(['kind' => 1, 'organ_id' => $letter->organ_id ?: $firstOwner?->id]);
                } else {
                    $letter->update(['kind' => $kind]);
                }
                $this->sendLetterSuggestions($chatId, $letter, [], $messageId);
            }
            return;
        }

        // تغییر دفتر یا ارگان گیرنده نامه
        if (str_starts_with($callbackData, 'letter_set|')) {
            $parts = explode('|', $callbackData);
            $letter = Letter::find((int) ($parts[1] ?? 0));
            $field = $parts[2] ?? '';
            $value = (int) ($parts[3] ?? 0);
            if ($letter && $user->can('create_letter') && in_array($field, ['daftar_id', 'organ_id', 'organ_owner'], true)) {
                $organ = Organ::find($value);
                $validOffice = $field === 'daftar_id' && $organ && (int) $organ->organ_type_id === 20;
                $validRecipient = $field === 'organ_id' && $organ && (int) $organ->organ_type_id !== 20 && (int) $letter->kind === 1;
                $validOwner = $field === 'organ_owner' && $organ && (int) $organ->organ_type_id !== 20 && (int) $letter->kind === 0;
                if ($validOffice) {
                    $letter->update(['daftar_id' => $value]);
                } elseif ($validRecipient) {
                    $letter->update(['organ_id' => $value]);
                } elseif ($validOwner) {
                    $exists = $letter->organs_owner()->where('organs.id', $value)->exists();
                    $exists ? $letter->organs_owner()->detach($value) : $letter->organs_owner()->syncWithoutDetaching([$value]);
                } else {
                    return;
                }
                $this->sendLetterSuggestions($chatId, $letter, [], $messageId);
            }
            return;
        }

        if (str_starts_with($callbackData, 'toggle_organ|')) {
            $parts = explode('|', $callbackData);
            $minute = Minutes::find((int) ($parts[1] ?? 0));
            $organId = (int) ($parts[2] ?? 0);
            $organ = Organ::find($organId);
            if (!$minute || !$organ || !$user->can('create_minutes')) return;

            $exists = $minute->organ()->where('organs.id', $organId)->exists();
            if ($exists) {
                $minute->organ()->detach($organId);
            } else {
                $minute->organ()->attach($organId);
            }
            $this->sendClassificationSuggestion($chatId, $minute, $messageId, [Project::class, MinutesGroup::class]);
            return;
        }

        if (str_starts_with($callbackData, 'toggle_content_group|')) {
            $parts = explode('|', $callbackData);
            $content = Content::find((int) ($parts[1] ?? 0));
            $groupId = (int) ($parts[2] ?? 0);
            $group = ContentGroup::find($groupId);
            if (!$content || !$group || !$user->can('create_content')) return;

            $exists = $content->group()->where('content_groups.id', $groupId)->exists();
            if ($exists) {
                $content->group()->detach($groupId);
            } else {
                $content->group()->attach($groupId);
            }
            $this->sendContentGroupSuggestions($chatId, $content, $messageId, (int) ($parts[3] ?? 1));
            return;
        }

        if (str_starts_with($callbackData, 'content_group_page_')) {
            $parts = explode('|', $callbackData, 2);
            $page = (int) str_replace('content_group_page_', '', $parts[0]);
            $contentId = (int) ($parts[1] ?? 0);
            $content = Content::find($contentId);
            if ($content && $user->can('create_content')) {
                $this->sendContentGroupSuggestions($chatId, $content, $messageId, max(1, $page));
            }
            return;
        }

        if (str_starts_with($callbackData, 'toggle_category|')) {
            $parts = explode('|', $callbackData);
            $filterModelTypes = null;

            if (count($parts) >= 6) {
                // پشتیبانی از callbackهای قدیمی ذخیره‌شده در پیام‌های قبلی.
                [, $modelClass, $modelId, $typeClass, $typeId, $filterJson] = $parts;
                $filterModelTypes = json_decode($filterJson, true);
            } else {
                // callback کوتاه‌تر برای رعایت محدودیت طول دکمه‌های بله.
                [, $modelKey, $modelId, $typeKey, $typeId] = $parts;
                $modelClass = [
                    'minutes' => Minutes::class,
                    'letter' => Letter::class,
                    'task' => Task::class,
                ][$modelKey] ?? null;
                $typeClass = [
                    'project' => Project::class,
                    'task_group' => \App\Models\TaskGroup::class,
                    'minutes_group' => MinutesGroup::class,
                ][$typeKey] ?? null;

                $filterModelTypes = match ($modelClass) {
                    Minutes::class => [Project::class, MinutesGroup::class],
                    Letter::class => [Project::class],
                    Task::class => [Project::class, \App\Models\TaskGroup::class],
                    default => null,
                };
            }

            if (!$modelClass || !$typeClass) return;
            $modelsub = app($modelClass)::find($modelId);
            if (!$modelsub) return;

            $relationMap = [
                \App\Models\Project::class => [
                    'relation' => $modelsub instanceof Minutes ? 'projects' : 'project',
                    'foreign_key' => 'project_id',
                    'label' => 'دستورکارها',
                ],
                \App\Models\TaskGroup::class => [
                    'relation' => 'group',
                    'foreign_key' => 'task_group_id',
                    'label' => 'دسته‌بندی‌ها',
                ],
                \App\Models\MinutesGroup::class => [
                    'relation' => 'group',
                    'foreign_key' => 'minutes_group_id',
                    'label' => 'دسته‌بندی صورت‌جلسه',
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
        if ($modelsub instanceof Minutes) {
            $title = trim($title . ' ' . ($modelsub->text ?? ''));
        }
        $filterModelTypes ??= match (true) {
            $modelsub instanceof Minutes => [Project::class, MinutesGroup::class],
            $modelsub instanceof Letter => [Project::class],
            $modelsub instanceof Task => [Project::class, \App\Models\TaskGroup::class],
            default => [Project::class],
        };

        $classifier = app(\App\Services\AiKeywordClassifier::class);
        $results = $classifier->classify($title, 0.1, $filterModelTypes, null, 3);

        // نگاشت مدل به: رابطه، کلید، و عنوان
        $relationMap = [
            \App\Models\Project::class => [
                'relation' => $modelsub instanceof Minutes ? 'projects' : 'project',
                'foreign_key' => 'project_id',
                'label' => 'دستورکارها',
            ],
            \App\Models\TaskGroup::class => [
                'relation' => 'group',
                'foreign_key' => 'task_group_id',
                'label' => 'دسته‌بندی‌ها',
            ],
            \App\Models\MinutesGroup::class => [
                'relation' => 'group',
                'foreign_key' => 'minutes_group_id',
                'label' => 'دسته‌بندی صورت‌جلسه',
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
                if ($model instanceof Project && $model->city) {
                    $modelTitle .= ' - ' . $model->city->name;
                }

                $isSelected = $modelsub->{$data['relation']}()
                    ->where($data['foreign_key'], $modelId)
                    ->exists();

                $text = ($isSelected ? '✅ ' : '') . $modelId . '-' . $modelTitle;
                $modelKey = $modelsub instanceof Minutes ? 'minutes' : ($modelsub instanceof Letter ? 'letter' : ($modelsub instanceof Task ? 'task' : null));
                $typeKey = match ($modelType) {
                    \App\Models\Project::class => 'project',
                    \App\Models\TaskGroup::class => 'task_group',
                    \App\Models\MinutesGroup::class => 'minutes_group',
                    default => null,
                };
                if (!$modelKey || !$typeKey) continue;
                $callback_data = "toggle_category|{$modelKey}|{$modelsub->id}|{$typeKey}|{$modelId}";

                $keyboard['inline_keyboard'][][] = [
                    'text' => $text,
                    'callback_data' => $callback_data,
                ];
            }
        }

        // پیشنهاد امضاکنندگان صورت‌جلسه از روی متن کامل و نام ارگان‌های ثبت‌شده.
        if ($modelsub instanceof Minutes) {
            $organSuggestions = $this->findMinuteOrganSuggestions($modelsub);
            if ($organSuggestions->isNotEmpty()) {
                $keyboard['inline_keyboard'][] = [
                    ['text' => '--- 🏢 امضاکنندگان پیشنهادی ---', 'callback_data' => '__noop__']
                ];
                foreach ($organSuggestions as $organ) {
                    $isSelected = $modelsub->organ()->where('organs.id', $organ->id)->exists();
                    $label = ($isSelected ? '✅ ' : '') . $organ->id . ' - ' . mb_substr($organ->name, 0, 42);
                    $keyboard['inline_keyboard'][] = [[
                        'text' => $label,
                        'callback_data' => "toggle_organ|{$modelsub->id}|{$organ->id}",
                    ]];
                }
            }
        }

        $keyboard['inline_keyboard'][] = [
            ['text' => '❌ حذف پیام', 'callback_data' => 'delete_message']
        ];

        $textMessage = $modelsub instanceof Minutes ? "📌 پیشنهادهای دستورکار، دسته‌بندی و امضاکنندگان صورت‌جلسه:" . "\n" : "📌 پیشنهادهای هوشمند برای رکورد:" . "\n";
        $hasOrganSuggestions = $modelsub instanceof Minutes && isset($organSuggestions) && $organSuggestions->isNotEmpty();
        $textMessage .= empty($results) && !$hasOrganSuggestions
            ? "برای این عنوان پیشنهاد مشابهی در واژه‌نامه هوشمند یا فهرست ارگان‌ها پیدا نشد."
            : "موارد مناسب را با لمس دکمه‌ها انتخاب یا از انتخاب خارج کنید.";
        if (is_null($messageId)) {
            $this->sendMessageWithKeyboard($chatId, $textMessage, $keyboard);
        } else {
            $token = config('bale.token');
            Http::post("https://tapi.bale.ai/bot{$token}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $textMessage,
                'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /** پیشنهاد ارگان‌های موجود با تطبیق کلمات کلیدی متن صورت‌جلسه. */
    private function findMinuteOrganSuggestions(Minutes $minute)
    {
        $text = trim(($minute->title ?? '') . ' ' . ($minute->text ?? ''));
        $classifier = app(\App\Services\AiKeywordClassifier::class);
        $keywords = array_values(array_unique($classifier->extractKeywords($text)));
        if (empty($keywords)) return collect();

        // فهرست ارگان‌ها مستقیماً از جدول اصلی خوانده می‌شود تا تفاوت نیم‌فاصله،
        // شکل جمع و حروف عربی/فارسی باعث از دست رفتن تطبیق نشود.
        $candidates = Organ::query()->select(['id', 'name'])->get();
        $normalizedKeywords = collect($keywords)->map(fn ($word) => $this->normalizeSuggestionWord($word))->filter()->unique()->values();

        return $candidates->map(function ($organ) use ($normalizedKeywords, $text) {
            $organWords = collect(preg_split('/\s+/u', $this->normalizeSuggestionText($organ->name)))
                ->map(fn ($word) => $this->normalizeSuggestionWord($word))
                ->filter(fn ($word) => mb_strlen($word) >= 3)->unique();
            $matched = $organWords->filter(function ($word) use ($normalizedKeywords) {
                return $normalizedKeywords->contains(fn ($keyword) =>
                    $keyword === $word || str_contains($keyword, $word) || str_contains($word, $keyword)
                );
            })->count();
            $score = $organWords->count() ? ($matched / $organWords->count()) : 0;
            $normalizedName = $this->normalizeSuggestionText($organ->name);
            if ($normalizedName !== '' && str_contains($this->normalizeSuggestionText($text), $normalizedName)) {
                $score += 1;
            }
            $organ->suggestion_score = $score;
            return $organ;
        })->filter(fn ($organ) => $organ->suggestion_score > 0)
            ->sortByDesc('suggestion_score')->take(5)->values();
    }

    private function normalizeSuggestionText(string $text): string
    {
        $text = strtr($text, ['ي' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه']);
        $text = preg_replace('/[‌ًٌٍَُِّْـ]/u', '', $text);
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($text)));
    }

    private function normalizeSuggestionWord(string $word): string
    {
        $word = $this->normalizeSuggestionText($word);
        foreach (['های', 'ها', 'ان'] as $suffix) {
            if (mb_strlen($word) > mb_strlen($suffix) + 2 && str_ends_with($word, $suffix)) {
                $word = mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
                break;
            }
        }
        return $word;
    }

    /** ثبت یادداشت از طریق ربات؛ فایل پاسخ‌شده و/یا متن همراه یادداشت را ذخیره می‌کند. */
    private function handleContent_create(string $messageText, $chatId, $user, ?array $doc = null): Content|false
    {
        if (!$user->can('create_content')) {
            $this->sendMessage($chatId, '❌ شما برای ثبت یادداشت دسترسی ندارید!');
            return false;
        }

        $lines = preg_split('/\r\n|\r|\n/u', trim($messageText));
        $firstLine = trim($lines[0] ?? '');
        $title = trim(preg_replace('/^#یادداشت\s*/u', '', $firstLine));
        $bodyText = trim(implode("\n", array_slice($lines, 1)));
        if ($title === '' && $bodyText !== '') {
            $bodyLines = preg_split('/\r\n|\r|\n/u', $bodyText);
            $title = trim(array_shift($bodyLines) ?? '');
            $bodyText = trim(implode("\n", $bodyLines));
        }
        if ($title === '') {
            $title = $doc ? 'یادداشت ثبت‌شده از ربات بله' : '';
        }
        if ($title === '') {
            $this->sendMessage($chatId, 'لطفاً عنوان یادداشت را بعد از #یادداشت بنویسید.');
            return false;
        }
        if (!$doc && $bodyText === '') {
            $this->sendMessage($chatId, 'برای ثبت یادداشت، متن یادداشت را در خط بعد بنویسید یا دستور را در پاسخ یک فایل/تصویر ارسال کنید.');
            return false;
        }

        try {
            $body = [];
            if ($bodyText !== '') {
                $body[] = ['type' => 'text', 'text' => $bodyText];
            }
            $record = Content::create([
                'title' => $title,
                'body' => $body,
                'user_id' => $user->id,
            ]);

            if ($doc && !empty($doc['file_id'])) {
                $contents = $this->getFile($doc['file_id']);
                if (!is_string($contents) || $contents === '') {
                    throw new \RuntimeException('دریافت فایل یادداشت از بله ناموفق بود.');
                }
                $extension = strtolower(pathinfo($doc['file_name'] ?? 'attachment.bin', PATHINFO_EXTENSION)) ?: 'bin';
                $filename = 'bale_' . $record->id . '_' . now()->format('YmdHis') . '.' . $extension;
                $path = $record->id . '/' . $filename;
                if (!Storage::disk('private2')->put($path, $contents)) {
                    throw new \RuntimeException('ذخیره فایل یادداشت ناموفق بود.');
                }
                $body[] = ['type' => 'file', 'file' => $path];
                $record->update(['body' => $body]);
            }

            $this->sendMessage($chatId,
                '✅ یادداشت با موفقیت ثبت شد.' . "\n" .
                '[بازکردن در سامانه](' . ContentResource::getUrl('edit', [$record->id]) . ')' . "\n\n" .
                'اکنون می‌توانید دسته‌بندی مناسب را انتخاب کنید.'
            );
            return $record;
        } catch (\Throwable $exception) {
            Log::error('Bale content creation failed.', [
                'user_id' => $user->id,
                'title' => $title,
                'error' => $exception->getMessage(),
            ]);
            $this->sendMessage($chatId, '❌ ثبت یادداشت با خطا مواجه شد. لطفاً دوباره تلاش کنید.');
            return false;
        }
    }

    private function sendContentGroupSuggestions($chatId, Content $record, $messageId = null, int $page = 1): void
    {
        $perPage = 12;
        $total = ContentGroup::query()->count();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $totalPages);
        $groups = ContentGroup::query()->with('parent')->orderBy('name')->forPage($page, $perPage)->get();
        $keyboard = ['inline_keyboard' => []];

        foreach ($groups as $group) {
            $selected = $record->group()->where('content_groups.id', $group->id)->exists();
            $label = ($selected ? '✅ ' : '') . $group->id . ' - ';
            if ($group->parent) $label .= $group->parent->name . ' / ';
            $label .= $group->name;
            $keyboard['inline_keyboard'][] = [[
                'text' => mb_substr($label, 0, 58),
                'callback_data' => "toggle_content_group|{$record->id}|{$group->id}|{$page}",
            ]];
        }
        $navigation = [];
        if ($page > 1) $navigation[] = ['text' => 'قبلی', 'callback_data' => 'content_group_page_' . ($page - 1) . '|' . $record->id];
        if ($page < $totalPages) $navigation[] = ['text' => 'بعدی', 'callback_data' => 'content_group_page_' . ($page + 1) . '|' . $record->id];
        if ($navigation) $keyboard['inline_keyboard'][] = $navigation;
        $keyboard['inline_keyboard'][] = [['text' => '❌ حذف پیام', 'callback_data' => 'delete_message']];

        $message = "📂 دسته‌بندی یادداشت «{$record->title}»\n";
        $message .= $total === 0
            ? 'هنوز دسته‌بندی‌ای تعریف نشده است.'
            : "دسته‌بندی مناسب را انتخاب کنید. موارد انتخاب‌شده با علامت ✅ مشخص هستند. (صفحه {$page} از {$totalPages})";

        if ($messageId === null) {
            $this->sendMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            $token = config('bale.token');
            Http::post("https://tapi.bale.ai/bot{$token}/editMessageText", [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
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
            $token = config('bale.token');
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

            }elseif ($queryText == 'یادداشت'){
                $message = <<<TEXT
راهنمای ثبت یادداشت

✅ #یادداشت عنوان یادداشت
برای ثبت یادداشت متنی، عنوان را در خط اول و متن یادداشت را در خط‌های بعدی بنویسید:

#یادداشت گزارش بازدید
متن یادداشت در این قسمت نوشته می‌شود.

📎 برای ضمیمه کردن فایل یا تصویر، همین دستور را در پاسخ (Reply) فایل/تصویر ارسال کنید:

#یادداشت گزارش بازدید

پس از ثبت، ربات فهرست دسته‌بندی‌های یادداشت را نمایش می‌دهد. با لمس هر دسته‌بندی می‌توانید آن را انتخاب یا از انتخاب خارج کنید. این امکان فقط برای کاربرانی فعال است که دسترسی «ایجاد یادداشت» دارند.
TEXT;

            }elseif ($queryText == 'نامه'){
                $message = <<<TEXT
راهنمای نامه

✅ #نامه یا #مکاتبه
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
باید در اولین خط کپشن فایل نامه آورده شود و با #نامه یا #مکاتبه شروع شود. در ادامه به صورت اختیاری می‌توان شماره مکاتبه فیزیکی نامه را آورد.
اگر فایل یا تصویر نامه همراه پیام باشد، ربات تلاش می‌کند متن آن را با OCR استخراج و اطلاعات نامه را تکمیل کند. در صورت شکست OCR، ثبت نامه با اطلاعات کپشن و روش معمول ادامه می‌یابد.
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
#نامه یا #مکاتبه
ایجاد نامه

✅ لیست دستورات نمایش اطلاعات :
/راهنما
این دستور راهنمای ربات را ارسال می کند . در ادامه دستور عنوان هر موجودیت شامل کار ، نامه ، صورتجلسه و... را وارد کنید راهنمای آن موجودیت را برای شما ارسال می شود.
 /یادداشت
نمایش ۵ یادداشت آخر شما؛ با افزودن عنوان یا بخشی از آن می‌توانید جستجو کنید.
برای راهنمای ثبت یادداشت: /راهنما یادداشت

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

    /** Normalize Bale document/photo payloads to the structure used by file storage. */
    private function getBaleAttachmentFromMessage(array $message): ?array
    {
        if (!empty($message['document']['file_id'])) {
            $document = $message['document'];
            if (empty($document['file_name'])) {
                $document['file_name'] = 'document_' . ($message['message_id'] ?? uniqid()) . '.bin';
            }
            return $document;
        }

        $photos = $message['photo'] ?? [];
        if (is_array($photos) && count($photos) > 0) {
            // Bale lists image variants from smaller to larger; use the last/highest resolution.
            $photo = end($photos);
            if (is_array($photo) && !empty($photo['file_id'])) {
                return [
                    'file_id' => $photo['file_id'],
                    'file_name' => 'photo_' . ($message['message_id'] ?? uniqid()) . '.jpg',
                ];
            }
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

            // Persist the minutes, its relations and generated tasks atomically.
            // If one step fails, the database is rolled back instead of leaving a
            // half-created minutes record with only some of its approvals.
            $record = DB::transaction(function () use ($mdata, $parsedData, $user) {
                $record = Minutes::create($mdata);
                $record->organ()->attach($parsedData['organs'] ?? []);
                $record->group()->attach(1);

                foreach (($parsedData['approves'] ?? []) as $approve) {
                    $cp = new \App\Http\Controllers\ai\CategoryPredictor();
                    $keywords = $cp->extractKeywords($approve['text'] ?? '');
                    $task = Task::create([
                        'name' => $approve['text'] ?? '',
                        'started_at' => $mdata['date'],
                        'created_at' => $mdata['date'],
                        'amount' => $approve['amount'] ?? null,
                        'ended_at' => $approve['due_at'] ?? null,
                        'Responsible_id' => $approve['user']['id'] ?? $user->id,
                        'created_by' => $user->id,
                        'minutes_id' => $record->id,
                        'city_id' => $cp->detectCity($keywords),
                        'organ_id' => $cp->detectOrgan($keywords),
                    ]);
                    $task->group()->attach([33, 32]); // دسته بندی هوش مصنوعی و مصوبه
                    $task->project()->attach($approve['projects'] ?: ($parsedData['global_projects'] ?? []));
                }

                return $record;
            });

            // ثبت صورت‌جلسه فقط بر اساس اطلاعات کپشن انجام می‌شود.
            // OCR و استخراج خودکار متن/مصوبات از فایل پیوست عمداً غیرفعال است.

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



        } finally {
            // Failure to send/delete the temporary progress message must not mask
            // the persisted Minutes record or turn a successful operation into failure.
            if (is_string($pmID) && $pmID !== '') {
                try {
                    $this->deleteMessage((string) $chatId, $pmID);
                } catch (\Throwable $cleanupException) {
                    Log::warning('Could not delete Bale minute processing message.', [
                        'chat_id' => $chatId,
                        'message_id' => $pmID,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }
        }

        return $record;
    }

    private function MinuteFileAdd($record, $doc, $media_group_id, $bale_user, $chatId = null)
    {
        if (!$record instanceof Minutes || empty($doc['file_id']) || empty($doc['file_name'])) {
            Log::warning('Bale minute file upload skipped: invalid record or document.', [
                'record_type' => is_object($record) ? get_class($record) : gettype($record),
                'record_id' => $record instanceof Minutes ? $record->id : null,
                'has_file_id' => !empty($doc['file_id']),
                'has_file_name' => !empty($doc['file_name']),
            ]);

            if ($chatId !== null) {
                $this->sendMessage($chatId, '⚠️ صورت‌جلسه پردازش شد، اما اطلاعات فایل معتبر نبود و فایل ذخیره نشد.');
            }

            return false;
        }

        try {
            $extension = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
            $fileContents = $this->getFile($doc['file_id']);

            if (!is_string($fileContents) || $fileContents === '') {
                throw new \RuntimeException('دریافت محتوای فایل صورت‌جلسه از بله ناموفق بود.');
            }

            $record->file = $extension;
            $record->save();

            $disk = Storage::disk('private_appendix_other');
            $stored = $disk->put($record->getFilePath(), $fileContents);

            if ($stored !== true) {
                throw new \RuntimeException('فضای ذخیره‌سازی فایل صورت‌جلسه عملیات نوشتن را تأیید نکرد.');
            }

            if ($media_group_id && $bale_user) {
                $state_data = explode('_', (string) $bale_user->state);

                if (($state_data[0] ?? null) == (string) $media_group_id
                    && !empty($state_data[1])
                    && !empty($state_data[2])) {
                    $child = $record::withoutEvents(function () use ($record, $state_data) {
                        return $record->appendix_others()->create([
                            'title' => 'ضمیمه',
                            'file' => $state_data[2],
                        ]);
                    });

                    $childContents = $this->getFile($state_data[1]);
                    if (!is_string($childContents) || $childContents === '') {
                        throw new \RuntimeException('دریافت فایل ضمیمه صورت‌جلسه از بله ناموفق بود.');
                    }

                    $childStored = $disk->put($child->getFilePath(), $childContents);
                    if ($childStored !== true) {
                        throw new \RuntimeException('ذخیره فایل ضمیمه صورت‌جلسه ناموفق بود.');
                    }
                }
            }

            if ($bale_user) {
                $bale_user->update(['state' => '1']);
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('Bale minute file storage failed.', [
                'minutes_id' => $record->id,
                'file_name' => $doc['file_name'] ?? null,
                'file_id' => $doc['file_id'] ?? null,
                'exception' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if ($chatId !== null) {
                $this->sendMessage(
                    $chatId,
                    '⚠️ اطلاعات صورت‌جلسه ثبت شد، اما ذخیره فایل یا ضمیمه با خطا مواجه شد. لطفاً فایل را دوباره ارسال کنید. جزئیات خطا در گزارش سامانه ثبت شد.'
                );
            }

            return false;
        }
    }

    private function handleLetter_create($caption, $chatId, $user, $isPrivateChat, ?array $doc = null)
    {
        if (!$user->can('create_letter')) {
            $this->sendMessage($chatId, '❌ شما برای ایجاد نامه دسترسی ندارید!');
            return false;
        }

        $ltp = new LetterParser();
        $sourceText = trim((string) $caption);
        $ocrText = '';
        $ocrSucceeded = false;

        // OCR فقط یک منبع تکمیلی است؛ شکست آن نباید ثبت نامه را متوقف کند.
        if ($doc && !empty($doc['file_id'])) {
            try {
                $fileContents = $this->getFile($doc['file_id']);
                if (!is_string($fileContents) || $fileContents === '') {
                    throw new \RuntimeException('دریافت فایل نامه از بله ناموفق بود.');
                }

                $ocrText = app(\App\Services\BaleLetterOcrService::class)
                    ->extract($fileContents, $doc['file_name'] ?? 'letter.pdf');
                $ocrSucceeded = trim($ocrText) !== '';
            } catch (\Throwable $ocrException) {
                Log::error('Bale letter OCR failed; continuing with caption parser.', [
                    'user_id' => $user->id,
                    'file_name' => $doc['file_name'] ?? null,
                    'error' => $ocrException->getMessage(),
                    'trace' => $ocrException->getTraceAsString(),
                ]);
                $this->sendMessage($chatId, '⚠️ متن فایل نامه استخراج نشد؛ ثبت نامه طبق اطلاعات کپشن و روش معمول ادامه پیدا می‌کند.');
            }
        }

        // متن OCR به parser ساختاریِ محلی داده نمی‌شود تا خطاهای تشخیص تصویر
        // باعث ایجاد خودکار صاحب/شخص اشتباه در بانک اطلاعاتی نشود؛ AI آن را جداگانه تحلیل می‌کند.
        $parseText = $sourceText;

        try {
            $dataLetter = $ltp->parse($parseText);
        } catch (\Throwable $parseException) {
            Log::error('Bale letter parsing failed; using minimal data.', [
                'user_id' => $user->id,
                'error' => $parseException->getMessage(),
                'trace' => $parseException->getTraceAsString(),
            ]);
            $firstLine = trim((preg_split('/\R/u', $sourceText)[0] ?? ''));
            $dataLetter = [
                'title' => trim(preg_replace('/^#(?:نامه|مکاتبه)\s*/u', '', $firstLine)) ?: 'نامه ثبت‌شده از ربات بله',
                'title_date' => null,
                'organ_id' => null,
                'user_id' => [],
                'kind' => 1,
                'pirow' => null,
                'mokatebe' => null,
                'daftar' => 461,
                'summary' => '',
                'description' => $ocrSucceeded ? $ocrText : $sourceText,
                'organ_owners' => [],
                'customer_owners' => [],
                'status' => 2,
                'projects' => [],
            ];
        }

        // تلاش تکمیلی هوش مصنوعی روی متن OCR؛ خروجی آن فقط برای تکمیل فیلدهای خالی استفاده می‌شود.
        if ($ocrSucceeded) {
            try {
                $aiData = $ltp->aiParse($ocrText);
                if (is_array($aiData) && trim((string) ($aiData['subject'] ?? '')) !== '') {
                    $dataLetter['title'] = $dataLetter['title'] ?: $aiData['subject'];
                    if (empty($dataLetter['title_date']) && !empty($aiData['date'])) {
                        try {
                            $rawDate = str_replace('-', '/', trim((string) $aiData['date']));
                            $dataLetter['title_date'] = preg_match('/^(13|14)\d{2}\/\d{1,2}\/\d{1,2}$/u', $rawDate)
                                ? Jalalian::fromFormat('Y/m/d', $rawDate)->toCarbon()
                                : Carbon::parse($aiData['date']);
                        } catch (\Throwable $dateException) {
                            Log::warning('Could not normalize OCR letter date.', [
                                'letter_date' => $aiData['date'],
                                'error' => $dateException->getMessage(),
                            ]);
                        }
                    }
                    $dataLetter['description'] = trim((string) ($dataLetter['description'] ?? '')) !== ''
                        ? $dataLetter['description']
                        : ($aiData['description'] ?? $ocrText);
                    $dataLetter['summary'] = trim((string) ($dataLetter['summary'] ?? '')) !== ''
                        ? $dataLetter['summary']
                        : ($aiData['summary'] ?? '');
                    $dataLetter['mokatebe'] = $dataLetter['mokatebe'] ?? ($aiData['mokatebe'] ?? null);
                    $dataLetter['organ_id'] = $dataLetter['organ_id'] ?? ($aiData['organ_id'] ?? null);
                    $dataLetter['kind'] = $dataLetter['kind'] ?? ($aiData['kind'] ?? 1);
                    $dataLetter['organ_owners'] = array_values(array_unique(array_merge(
                        $dataLetter['organ_owners'] ?? [],
                        $aiData['organ_owners'] ?? []
                    )));
                    $dataLetter['customer_owners'] = array_values(array_unique(array_merge(
                        $dataLetter['customer_owners'] ?? [],
                        $aiData['customer_owners'] ?? []
                    )));
                }
            } catch (\Throwable $aiException) {
                Log::warning('Bale letter AI enrichment failed; local parser data retained.', [
                    'user_id' => $user->id,
                    'error' => $aiException->getMessage(),
                ]);
            }

            // متن OCR به‌عنوان متن اصلی نامه حفظ می‌شود تا اطلاعات استخراج‌شده از بین نرود.
            if (trim((string) ($dataLetter['description'] ?? '')) === '') {
                $dataLetter['description'] = $ocrText;
            }
        }

        if (trim((string) ($dataLetter['title'] ?? '')) === '') {
            $firstLine = trim((preg_split('/\R/u', $sourceText)[0] ?? ''));
            $fallbackTitle = trim(preg_replace('/^#(?:نامه|مکاتبه)\s*/u', '', $firstLine));
            $dataLetter['title'] = $fallbackTitle ?: 'نامه ثبت‌شده از ربات بله';
        }

        $record = Letter::create([
            'subject' => trim((string) ($dataLetter['title'] ?? '')) ?: 'نامه ثبت‌شده از ربات بله',
            'created_at' => $dataLetter['title_date'] ?? Carbon::now(),
            'description' => $dataLetter['description'] ?? $sourceText,
            'summary' => $dataLetter['summary'] ?? '',
            'mokatebe' => $dataLetter['mokatebe'] ?? null,
            'daftar_id' => $dataLetter['daftar'] ?? 461,
            'kind' => $dataLetter['kind'] ?? 1,
            'user_id' => $user->id,
            'peiroow_letter_id' => $dataLetter['pirow'] ?? null,
            'status' => $dataLetter['status'] ?? 2,
            'organ_id' => ($dataLetter['kind'] ?? 1) == 1 ? ($dataLetter['organ_id'] ?? null) : null,
        ]);

        if (($dataLetter['kind'] ?? 1) == 0 && !empty($dataLetter['organ_id'])) {
            $record->organs_owner()->syncWithoutDetaching(array_filter([$dataLetter['organ_id']]));
        }

        $record->users()->syncWithoutDetaching($dataLetter['user_id'] ?? []);
        $record->organs_owner()->syncWithoutDetaching(array_filter($dataLetter['organ_owners'] ?? []));
        $record->customers()->syncWithoutDetaching(array_filter($dataLetter['customer_owners'] ?? []));
        $record->projects()->syncWithoutDetaching(array_filter($dataLetter['projects'] ?? []));

        $message = '';
        if ($isPrivateChat) {
            $message .= '✉️ اطلاعات نامه ذخیره شد' . "\n\n";
            $message .= '[بازکردن در سامانه](' . LetterResource::getUrl('edit', [$record->id]) . ')' . "\n\n";
            $message .= $this->CreateLetterMessage($record);
        } else {
            $message .= '✉️ [نامه با شماره ' . $record->id . ' ثبت شد .](' . LetterResource::getUrl('edit', [$record->id]) . ')';
        }
        if ($ocrSucceeded) {
            $message .= "\n\n🔎 متن فایل با OCR استخراج و در تحلیل نامه استفاده شد.";
        }
        $this->sendMessage($chatId, $message);

        $this->sendLetterSuggestions($chatId, $record, $dataLetter);
        return $record;
    }

    /** نمایش آخرین یادداشت‌ها یا جستجو در یادداشت‌های متعلق به کاربر. */
    private function handleContent_list($chatId, $user, string $queryText = ''): void
    {
        if (!$user->can('view_any_content') && !$user->can('view_content') && !$user->can('create_content')) {
            $this->sendMessage($chatId, '❌ شما دسترسی مشاهده یادداشت‌ها را ندارید.');
            return;
        }

        $query = Content::query()->where('user_id', $user->id)->orderByDesc('id');
        if ($queryText !== '') {
            $query->where('title', 'like', '%' . str_replace(' ', '%', $queryText) . '%');
        }
        $items = $query->limit(5)->get();

        if ($items->isEmpty()) {
            $this->sendMessage($chatId, $queryText !== ''
                ? '🔎 یادداشتی با این عنوان پیدا نشد.'
                : '🗒️ هنوز یادداشتی برای شما ثبت نشده است.');
            return;
        }

        $message = "🗒️ یادداشت‌های شما" . ($queryText !== '' ? " (نتیجه جستجو)" : '') . "\n\n";
        foreach ($items as $index => $item) {
            $message .= ($index + 1) . ") " . $item->title . "\n";
            $message .= "🆔 " . $item->id . "\n";
            $groups = $item->group()->pluck('name')->all();
            if ($groups) $message .= "📂 " . implode('، ', $groups) . "\n";
            $message .= '[بازکردن یادداشت](' . ContentResource::getUrl('edit', [$item->id]) . ')' . "\n\n";
        }
        $this->sendMessage($chatId, trim($message));
    }

    /** پیشنهاد ارگان‌های مرتبط با متن نامه، با همان نرمال‌سازی مورد استفاده در پیشنهادهای صورت‌جلسه. */
    private function findLetterOrganSuggestions(Letter $letter)
    {
        $text = trim(($letter->subject ?? '') . ' ' . ($letter->description ?? ''));
        $classifier = app(\App\Services\AiKeywordClassifier::class);
        $keywords = collect($classifier->extractKeywords($text))
            ->map(fn ($word) => $this->normalizeSuggestionWord((string) $word))
            ->filter()
            ->unique()
            ->values();

        if ($keywords->isEmpty()) return collect();

        return Organ::query()
            ->where('organ_type_id', '!=', 20)
            ->select(['id', 'name'])
            ->get()
            ->map(function ($organ) use ($keywords) {
                $organWords = collect(preg_split('/\s+/u', $this->normalizeSuggestionText($organ->name)))
                    ->filter()
                    ->unique();
                $matches = $organWords->intersect($keywords)->count();
                $score = $organWords->isEmpty() ? 0 : $matches / $organWords->count();
                return ['organ' => $organ, 'matches' => $matches, 'score' => $score];
            })
            ->filter(fn ($item) => $item['matches'] >= 1)
            ->sortByDesc(fn ($item) => ($item['score'] * 100) + $item['matches'])
            ->take(20)
            ->pluck('organ')
            ->values();
    }

    /** پیشنهاد و امکان اصلاح فیلدهای اصلی نامه پس از ثبت. */
    private function sendLetterSuggestions($chatId, Letter $letter, array $parsed = [], $messageId = null): void
    {
        $keyboard = ['inline_keyboard' => []];
        $kindLabel = (int) $letter->kind === 1 ? 'صادره' : 'وارده';
        $keyboard['inline_keyboard'][] = [[
            'text' => "نوع نامه: {$kindLabel} (تغییر)",
            'callback_data' => "letter_kind|{$letter->id}|" . ((int) $letter->kind === 1 ? 0 : 1),
        ]];

        $daftar = Organ::query()->where('organ_type_id', 20)->orderBy('name')->get();
        if ($daftar->isNotEmpty()) {
            $keyboard['inline_keyboard'][] = [['text' => '--- 🏢 انتخاب دفتر ---', 'callback_data' => '__noop__']];
            foreach ($daftar as $item) {
                $selected = (int) $letter->daftar_id === (int) $item->id;
                $keyboard['inline_keyboard'][] = [[
                    'text' => ($selected ? '✅ ' : '') . mb_substr($item->name, 0, 48),
                    'callback_data' => "letter_set|{$letter->id}|daftar_id|{$item->id}",
                ]];
            }
        }

        try {
            $organCandidates = $this->findLetterOrganSuggestions($letter);
        } catch (\Throwable $organSuggestionException) {
            Log::warning('Bale letter organ suggestions failed.', [
                'letter_id' => $letter->id,
                'error' => $organSuggestionException->getMessage(),
            ]);
            $organCandidates = collect();
        }
        if (!empty($parsed['organ_id'])) {
            $candidate = Organ::find($parsed['organ_id']);
            if ($candidate) $organCandidates->prepend($candidate);
        }
        $organCandidates = $organCandidates->unique('id')->take(20);

        if ($organCandidates->isNotEmpty()) {
            $keyboard['inline_keyboard'][] = [['text' => '--- 🏛 گیرنده / ارگان ---', 'callback_data' => '__noop__']];
            foreach ($organCandidates as $item) {
                $isIncoming = (int) $letter->kind === 0;
                $selected = $isIncoming
                    ? $letter->organs_owner()->where('organs.id', $item->id)->exists()
                    : (int) $letter->organ_id === (int) $item->id;
                $field = $isIncoming ? 'organ_owner' : 'organ_id';
                $keyboard['inline_keyboard'][] = [[
                    'text' => ($selected ? '✅ ' : '') . $item->id . ' - ' . mb_substr($item->name, 0, 38),
                    'callback_data' => "letter_set|{$letter->id}|{$field}|{$item->id}",
                ]];
            }
        }

        try {
            $projects = app(\App\Services\AiKeywordClassifier::class)
                ->classify(trim($letter->subject . ' ' . $letter->description), 0.1, [Project::class], null, 5);
        } catch (\Throwable $suggestionException) {
            Log::warning('Bale letter classification suggestions failed.', [
                'letter_id' => $letter->id,
                'error' => $suggestionException->getMessage(),
            ]);
            $projects = [];
        }
        $projectItems = $projects[Project::class] ?? [];
        if ($projectItems) {
            $keyboard['inline_keyboard'][] = [['text' => '--- 📁 دستورکارهای پیشنهادی ---', 'callback_data' => '__noop__']];
            foreach ($projectItems as $item) {
                $project = Project::find($item['model_id'] ?? 0);
                if (!$project) continue;
                $selected = $letter->projects()->where('projects.id', $project->id)->exists();
                $keyboard['inline_keyboard'][] = [[
                    'text' => ($selected ? '✅ ' : '') . $project->id . ' - ' . mb_substr(
                        $project->name . ($project->city?->name ? ' / ' . $project->city->name : ''),
                        0,
                        38
                    ),
                    'callback_data' => "toggle_category|letter|{$letter->id}|project|{$project->id}",
                ]];
            }
        }

        $keyboard['inline_keyboard'][] = [['text' => '❌ حذف پیام', 'callback_data' => 'delete_message']];
        $message = "📌 پیشنهادهای تکمیلی نامه شماره {$letter->id}\n";
        $message .= "دفتر، گیرنده و دستورکار را بررسی کنید؛ با لمس گزینه‌ها می‌توانید مقدار را انتخاب یا تغییر دهید.";
        if ($messageId === null) {
            $this->sendMessageWithKeyboard($chatId, $message, $keyboard);
        } else {
            Http::post('https://tapi.bale.ai/bot' . config('bale.token') . '/editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $message,
                'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
            ]);
        }
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
