<?php

namespace App\Http\Controllers\ai;

use App\Http\Controllers\ReadChanel;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Organ;
use Exception;
use Illuminate\Support\Facades\Cache;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;
use Carbon\Carbon;

class MinutesParser
{

    public function __construct(public bool $readChanelTasks = true)
    {
    }

    public function parse(string $text,int $user_id = 1,Carbon $titleDate = null): array
    {
        try {
            $lines = array_filter(array_map('trim', explode("\n", $text)));

            $titleLine = array_shift($lines);
            $title = $this->cleanTitle($titleLine);
            $titleDate = $titleDate ?? $this->extractDateFromTitle($title);

            $approves = [];
            $organs = [];
            $taskId = null;
            $globalProjects = [];
//        $organsName = [];

            foreach ($lines as $line) {
                if (str_starts_with($line, '-') || str_starts_with($line, '_') || str_starts_with($line, 'ـ')) {
                    $rawLine = ltrim($line, "-_ـ ");

                    $approve = [];

                    // استخراج @ها از approve
                    preg_match_all('/@\s*([^\s]+)/u', $rawLine, $matches);
                    foreach ($matches[1] as $mention) {
                        $name = trim(str_replace(['_','-'], ' ', $mention));
                        $user = User::where('name', 'like', "%$name%")
                            ->orWhere('id', $mention)
                            ->first();
                        if ($user) {
                            $approve['user'] = ['id' => $user->id, 'name' => $user->name];
                        }
                    }

                    // --- استخراج amount (اعداد با $) ---
                    $amount = null;
                    if (preg_match('/\$\s*([\d][\d,٫،.\s]*)/u', $rawLine, $m)) {
                        $amount = trim(preg_replace('/[^\d]/u', '', $m[1]));
                        // پاک کردن مقدار از متن
                        $rawLine = preg_replace('/\$\s*([\d][\d,٫،.\s]*)/u', '', $rawLine);
                    }
                    $approve['amount'] = $amount;

                    // --- استخراج پروژه‌ها / دستورکارها ---
                    [$approve['projects'],$rawLine] = $this->extractProjects($rawLine);

                    // حذف @ها از متن
                    $cleanLine = preg_replace('/@\s*([^\s]+)/u', '', $rawLine);
                    $approve['text'] = trim($cleanLine);

                    // استخراج تاریخ‌های نسبی
                    $approve['due_at'] = $this->extractRelativeDate($rawLine,$titleDate);

                    $approves[] = $approve;
                }
                else if (preg_match('/(?:ایجاد\s*جلسه|جلسه)(.*)/u', $line, $m)) {
                    $after = trim($m[1]);
                    $meetingTitle = null;
                    if ($after and $after != '') {
                        $meetingTitle = $after;
                    } else {
                        $meetingTitle = str_replace('صورتجلسه', 'جلسه', $title);
                    }

                    // ایجاد جلسه در دیتابیس
                    $catPreder = new CategoryPredictor();
                    $cats = $catPreder->predictWithCity($meetingTitle);
                    $time = $titleDate ?? Carbon::now();
                    $task = Task::create([
                        'name' => $meetingTitle,
                        'description' => $text,
                        'created_at' => $time,
                        'completed_at' => $time,
                        'started_at' => $time,
                        'completed' => 1,
                        'status' => 1,
                        'Responsible_id' => $user_id,
                        'city_id' => $cats['city'] ?? null,
                    ]);
                    if (isset($cats['categories'])){
                        $task->project()->attach($cats['categories']);
                    }
                    $task->group()->attach([1, 32]);

                    $taskId = $task->id;
                }
                else if ($titleDate === null) {
                    // تشخیص تاریخ در خط های بعدی
                    $maybeDate = $this->extractDateFromTitle($line);
                    if ($maybeDate) {
                        $titleDate = $maybeDate;
                    }
                }
                else{
                    // --- استخراج پروژه‌ها / دستورکارها ---
                    [$globalProjects,$line] = $this->extractProjects($line);

                    // استخراج @های مستقل برای organs
                    preg_match_all('/@\s*([^@]+)/u', $line, $organMatchesLine);
                    if (!empty($organMatchesLine[1])) {
                        foreach ($organMatchesLine[1] as $mention) {
                            $name = str_replace(['_', '-'], ' ', $mention);
                            $name = str_replace('@', '', $name);
                            $name = trim($name);
                            $organKeywords = explode(" ", $name);

                            $organs[] = $this->detectOrgan($organKeywords);
                        }
                    }

                }
            }

            if (!$taskId) {
                // اگر جلسه نبود، همان تسک دیتکت
                $taskId = $this->taskDetect($title);
            }
            $organs = array_unique($organs);

            return [
                'title' => $title,
                'title_date' => $titleDate,
                'approves' => $approves,
                'organs' => $organs,
                'task_id' => $taskId,
                'global_projects' => $globalProjects,
            ];
        } catch (Exception $exception) {
            throw new $exception;
        }
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
                return (new Jalalian( $matches[1], $matches[2], $matches[3]))->toCarbon()->setTimeFrom(Carbon::now());
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    protected function extractRelativeDate(string $text, Carbon $now): ?Carbon
    {
        $now = clone $now;

        // نگاشت پایه اعداد فارسی متنی
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
            'یازده' => 11, 'دوازده' => 12,
            'سیزده' => 13, 'چهارده' => 14,
            'پانزده' => 15, 'شانزده' => 16,
            'هفده' => 17, 'هجده' => 18,
            'نوزده' => 19, 'بیست' => 20,
            'سی' => 30, 'چهل' => 40,
            'پنجاه' => 50, 'شصت' => 60,
            'هفتاد' => 70, 'هشتاد' => 80,
            'نود' => 90, 'صد' => 100,
        ];

        // تابع کمکی برای تبدیل متن به عدد
        $convertTextToNumber = function(string $raw) use ($numberMap): ?int {
            $raw = trim($raw);

            // اگر مستقیم در نگاشت بود
            if (isset($numberMap[$raw])) {
                return $numberMap[$raw];
            }

            // اگر ترکیبی مثل "بیست و یک"
            if (strpos($raw, 'و') !== false) {
                $parts = array_map('trim', explode('و', $raw));
                $sum = 0;
                foreach ($parts as $p) {
                    if (isset($numberMap[$p])) {
                        $sum += $numberMap[$p];
                    } else {
                        return null;
                    }
                }
                return $sum;
            }

            return null;
        };

        // regex با کلیدواژه‌های مختلف
        if (preg_match('/(تا|مدت|ظرف|لغایت)\s*(\d+|[۰-۹]+|[آ-ی\s]+)\s*(روز|هفته|ماه|سال)/u', $text, $matches)) {
            $rawNumber = trim($matches[2]);
            $unit = $matches[3];

            // تبدیل اعداد فارسی به انگلیسی
            $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            $englishDigits = ['0','1','2','3','4','5','6','7','8','9'];
            $normalizedNumber = str_replace($persianDigits, $englishDigits, $rawNumber);

            // تبدیل به عدد صحیح
            $number = is_numeric($normalizedNumber)
                ? (int)$normalizedNumber
                : $convertTextToNumber($rawNumber);

            if ($number === null) {
                return null;
            }

            return match ($unit) {
                'روز'   => $now->addDays($number),
                'هفته'  => $now->addDays(7 * $number),
                'ماه'   => $now->addMonths($number),
                'سال'   => $now->addYears($number),
                default => null,
            };
        }

        return null;
    }

    public function taskDetect(string $title) : ?int
    {
        if ($this->readChanelTasks){
            try {
                //دریافت جلسات جدید از کانال
                (new ReadChanel())->read();
            }catch (Exception $e){
                echo $e->getMessage();
            }
        }

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

    /**
     * @param array|string|null $rawLine
     * @param $pm
     * @param array $projects_id
     * @return array
     */
    public function extractProjects(string $rawLine): array
    {
        $projects_id = [];
        if (preg_match('/(?:پروژه|دستور\s*کار)\s+(.+)/u', $rawLine, $pm)) {
            $content = trim($pm[1]);

            // جدا کردن چند پروژه با کاما فارسی یا نقطه
            $items = preg_split('/[،\.]+/u', $content);

            foreach ($items as $item) {
                $item = trim($item);
                if (!$item) continue;

                if (is_numeric($item)) {
                    $project = Project::find($item);
                    if ($project) {
                        $projects_id[] = $project->id;
                    }
                } else {
                    $project = Project::query()->where('name', 'like', '%' . $item . '%')->first();
                    if ($project) {
                        $projects_id[] = $project->id;
                    }
                }
            }

            // پاک کردن بخش پروژه/دستورکار از متن
            $rawLine = preg_replace('/(?:پروژه|دستور\s*کار)\s+.+/u', '', $rawLine);
        }
        return [array_unique($projects_id),$rawLine];
    }

    public function detectOrgan(array $keywords, int $minRequiredMatches = 2): ?int
    {
        $organs = Cache::remember('organ_records', 3600, function () {
            return Organ::select('id', 'name')->get();
        });

        $keywordSet = array_map('mb_strtolower', $keywords);

        $bestMatchId = null;
        $maxMatchCount = 0;

        foreach ($organs as $organ) {
            $organWords = preg_split('/\s+/', mb_strtolower($organ->name));
            $matchCount = count(array_intersect($organWords, $keywordSet));

            if ($matchCount > $maxMatchCount) {
                $maxMatchCount = $matchCount;
                $bestMatchId = $organ->id;
            }
        }

        // اگر هیچ تطابقی نداشت، null برمی‌گردد
        return $maxMatchCount >= $minRequiredMatches ? $bestMatchId : null;
    }
}
