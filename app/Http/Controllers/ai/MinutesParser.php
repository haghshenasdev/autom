<?php

namespace App\Http\Controllers\ai;

use App\Http\Controllers\ReadChanel;
use App\Models\Task;
use App\Models\User;
use App\Models\Organ;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;
use Carbon\Carbon;

class MinutesParser
{
    public function parse(string $text): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $text)));

        $titleLine = array_shift($lines);
        $title = $this->cleanTitle($titleLine);
        $titleDate = $this->extractDateFromTitle($title);

        $approves = [];
        $organs = [];
//        $organsName = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '-')) {
                $rawLine = ltrim($line, '- ');

                $approve = [];

                // استخراج @ها از approve
                preg_match_all('/@([\w_]+)/u', $rawLine, $matches);
                foreach ($matches[1] as $mention) {
                    $name = str_replace('_', ' ', $mention);
                    $user = User::where('name', 'like', "%$name%")
                        ->orWhere('id', $mention)
                        ->first();
                    if ($user) {
                        $approve['user'] = ['id' => $user->id, 'name' => $user->name];
                    }
                }

                // حذف @ها از متن
                $cleanLine = preg_replace('/@[\w_]+/u', '', $rawLine);
                $approve['text'] = trim($cleanLine);

                // استخراج تاریخ‌های نسبی
                $approve['due_at'] = $this->extractRelativeDate($rawLine,$titleDate);

                $approves[] = $approve;
            }else{
                // استخراج @های مستقل برای organs
                preg_match_all('/@[\w_]+/u', $line, $organMatchesLine);
                foreach ($organMatchesLine as $organMatches ){
                    foreach ($organMatches as $mention) {
                        $name = str_replace('_', '%', $mention);
                        $name = str_replace('-', '%', $name);
                        $name = str_replace('@', '', $name);
//                        $organsName[] = $name;
                        $organ = Organ::where('name', 'like', "%$name%")
                            ->orWhere('id', $mention)
                            ->first();
                        if ($organ) {
                            $organs[] = ['id' => $organ->id, 'name' => $organ->name];
                        }
                    }
                }

            }
        }

        return [
            'title' => $title,
            'title_date' => $titleDate,
            'approves' => $approves,
            'organs' => $organs,
            'task_id' => $this->taskDetect($title),
        ];
    }

    protected function cleanTitle(string $line): string
    {
        return ltrim($line, '# ');
    }

    protected function extractDateFromTitle(string $line): ?Carbon
    {
        $englishDigits = ['0','1','2','3','4','5','6','7','8','9'];
        $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $converted = str_replace($persianDigits, $englishDigits, $line);

        if (preg_match('/\b(\d{4})\/(\d{1,2})\/(\d{1,2})\b/u', $converted, $matches)) {
            try {
                return (new Jalalian((int) $matches[1], (int) $matches[2],(int) $matches[3]))->toCarbon()->setTimeFrom(Carbon::now());
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    protected function extractRelativeDate(string $text,Carbon $now): ?Carbon
    {
        // نگاشت اعداد فارسی متنی و عددی به عدد
        $numberMap = [
            'یک' => 1, '۱' => 1,
            'دو' => 2, '۲' => 2,
            'سه' => 3, '۳' => 3,
            'چهار' => 4, '۴' => 4,
            'پنج' => 5, '۵' => 5,
            'شش' => 6, '۶' => 6,
            'هفت' => 7, '۷' => 7,
            'هشت' => 8, '۸' => 8,
            'نه' => 9, '۹' => 9,
            'ده' => 10, '۱۰' => 10,
        ];

        // استخراج عدد و واحد زمانی با پشتیبانی از اعداد فارسی و متنی
        if (preg_match('/تا\s*(\d+|[۰-۹]+|یک|دو|سه|چهار|پنج|شش|هفت|هشت|نه|ده)\s*(روز|هفته|ماه|سال)/u', $text, $matches)) {
            $rawNumber = $matches[1];
            $unit = $matches[2];

            // تبدیل اعداد فارسی به انگلیسی
            $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            $englishDigits = ['0','1','2','3','4','5','6','7','8','9'];
            $normalizedNumber = str_replace($persianDigits, $englishDigits, $rawNumber);

            // تبدیل به عدد صحیح
            $number = is_numeric($normalizedNumber)
                ? (int)$normalizedNumber
                : ($numberMap[$rawNumber] ?? null);

            if ($number === null) {
                return null;
            }

            return match ($unit) {
                'روز' => $now->addDays($number),
                'هفته' => $now->addDays(7 * $number),
                'ماه' => $now->addMonths($number),
                'سال' => $now->addYears($number),
                default => null,
            };
        }

        return null;
    }

    public function taskDetect(string $title) : ?int
    {
        //دریافت جلسات جدید از کانال
        (new ReadChanel())->read();

        $cp = new \App\Http\Controllers\ai\CategoryPredictor();
        $MinuteKeywords = $cp->extractKeywords($title);
        $tasks = Task::query()->orderByDesc('id')->limit(10)->get();
        foreach ($tasks as $task) {
            $TaskKeywords = $cp->extractKeywords($task->name);
            if (count(array_intersect($TaskKeywords, $MinuteKeywords)) > 3) {
                return $task->id;
            }
        }
        return null;
    }
}
