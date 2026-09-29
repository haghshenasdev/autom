<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ai\CategoryPredictor;
use App\Http\Controllers\ai\LetterParser;
use App\Http\Controllers\MinuteTextPS;
use App\Models\Task;
use App\Models\Project;
use App\Models\TaskGroup;
use App\Models\MinutesGroup;
use App\Services\AiKeywordClassifier;
use App\Models\City;
use App\Models\Organ;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;
use Symfony\Component\HttpFoundation\Response;

class MobileAiController extends Controller
{
    /**
     * تحلیل عنوان فقط با مدل‌های داخلی دیتابیس؛ بدون فراخوانی API هوش مصنوعی.
     * خروجی برای فرم موبایل شامل شهر، ارگان، دستورکارها، دسته‌بندی‌ها و فعالیت‌های
     * پیشنهادی است.
     */
    public function title(Request $request)
    {
        $data = $request->validate([
            'text' => 'required|string|max:2000',
            'resource' => 'nullable|in:letters,minutes,tasks',
        ]);

        $title = trim($data['text']);
        $resource = $data['resource'] ?? 'letters';

        $predictor = new CategoryPredictor();
        $prediction = $predictor->predictWithCityOrgan($title) ?? [
            'categories' => [],
            'city' => null,
            'organ' => null,
        ];

        $classifier = app(AiKeywordClassifier::class);
        $classified = $classifier->classify(
            $title,
            0.10,
            [Project::class, TaskGroup::class, MinutesGroup::class],
            null,
            5
        );

        $projects = collect($classified[Project::class] ?? [])->map(function ($item) {
            $record = Project::find($item['model_id']);
            return $record ? [
                'id' => $record->id,
                'name' => $record->name,
                'percent' => $item['percent'],
                'score' => $item['score'],
            ] : null;
        })->filter()->values()->all();

        $taskGroups = collect($classified[TaskGroup::class] ?? [])->map(function ($item) {
            $record = TaskGroup::find($item['model_id']);
            return $record ? [
                'id' => $record->id,
                'name' => $record->name,
                'percent' => $item['percent'],
                'score' => $item['score'],
            ] : null;
        })->filter()->values()->all();

        $minuteGroups = collect($classified[MinutesGroup::class] ?? [])->map(function ($item) {
            $record = MinutesGroup::find($item['model_id']);
            return $record ? [
                'id' => $record->id,
                'name' => $record->name,
                'percent' => $item['percent'],
                'score' => $item['score'],
            ] : null;
        })->filter()->values()->all();

        $tasks = Task::query()->select(['id', 'name'])->latest('id')->limit(100)->get()->map(function ($task) use ($predictor, $title) {
            $score = count(array_intersect(
                $predictor->extractKeywords($task->name),
                $predictor->extractKeywords($title)
            ));
            return ['id' => $task->id, 'name' => $task->name, 'score' => $score];
        })->filter(fn ($x) => $x['score'] > 0)->sortByDesc('score')->take(5)->values()->all();

        return response()->json([
            'data' => [
                'title' => $title,
                'city_id' => $prediction['city'],
                'city_name' => $prediction['city'] ? City::find($prediction['city'])?->name : null,
                'organ_id' => $prediction['organ'],
                'organ_name' => $prediction['organ'] ? Organ::find($prediction['organ'])?->name : null,
                'project_ids' => collect($projects)->pluck('id')->all(),
                'projects' => $projects,
                'task_group_ids' => collect($taskGroups)->pluck('id')->all(),
                'task_groups' => $taskGroups,
                'minute_group_ids' => collect($minuteGroups)->pluck('id')->all(),
                'minute_groups' => $minuteGroups,
                'task_ids' => collect($tasks)->pluck('id')->all(),
                'tasks' => $tasks,
                'resource' => $resource,
            ],
        ]);
    }

    public function minute(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string',
            'file' => 'nullable|file|max:51200',
        ]);

        $text = trim((string) $request->input('text', ''));

        if ($request->hasFile('file')) {
            try {
                $text = trim($this->ocr($request->file('file')));
            } catch (\Throwable $e) {
                Log::error('Mobile OCR failed', ['message' => $e->getMessage()]);
                return response()->json(['message' => 'استخراج متن از فایل انجام نشد.'], 422);
            }
        }

        if ($text === '') {
            return response()->json(['message' => 'متن یا فایل برای تحلیل ارسال نشده است.'], 422);
        }

        $cleaned = $this->cleanMinuteText($text);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $cleaned))));
        $title = $lines[0] ?? '';

        $predictor = new CategoryPredictor();
        $prediction = $predictor->predictWithCityOrgan($title) ?? [
            'categories' => [],
            'city' => null,
            'organ' => null,
        ];

        return response()->json([
            'data' => [
                'title' => $title,
                'text' => $cleaned,
                'date' => $this->jalaliDateToIso($title),
                'city_id' => $prediction['city'] ?? null,
                'organ_id' => $prediction['organ'] ?? null,
                'category_ids' => $prediction['categories'] ?? [],
                'task_id' => $this->detectTask($title),
                'task_name' => $this->detectTaskName($title),
                'city_name' => $prediction['city'] ? City::find($prediction['city'])?->name : null,
                'organ_name' => $prediction['organ'] ? Organ::find($prediction['organ'])?->name : null,
                'category_names' => Project::whereIn('id', $prediction['categories'] ?? [])->pluck('name')->values()->all(),
            ],
        ]);
    }

    public function letter(Request $request)
    {
        $request->validate([
            'text' => 'nullable|string',
            'file' => 'nullable|file|max:51200',
        ]);

        $text = trim((string) $request->input('text', ''));

        if ($request->hasFile('file')) {
            try {
                $text = trim($this->ocr($request->file('file')));
            } catch (\Throwable $e) {
                Log::error('Mobile OCR failed', ['message' => $e->getMessage()]);
                return response()->json(['message' => 'استخراج متن از فایل انجام نشد.'], 422);
            }
        }

        if ($text === '') {
            return response()->json(['message' => 'متن یا فایل برای تحلیل ارسال نشده است.'], 422);
        }

        try {
            $parser = new LetterParser();
            $parsed = $parser->aiParse($text);
        } catch (\Throwable $e) {
            Log::error('Mobile letter AI failed', ['message' => $e->getMessage()]);
            return response()->json(['message' => 'تحلیل نامه انجام نشد.'], 422);
        }

        if (!is_array($parsed)) {
            $parsed = [];
        }

        // اگر سرویس بیرونی در دسترس نبود، تحلیل پایه LetterParser محلی را
        // نگه می‌داریم تا ثبت نامه متوقف نشود.
        if (trim((string) ($parsed['subject'] ?? '')) === '') {
            $local = (new LetterParser())->parse($text);
            $parsed = [
                'subject' => $local['title'] ?? '',
                'description' => $local['description'] ?? $text,
                'summary' => $local['summary'] ?? '',
                'mokatebe' => $local['mokatebe'] ?? null,
                'kind' => $local['kind'] ?? 1,
                'organ_id' => $local['organ_id'] ?? null,
                'organ_owners' => $local['organ_owners'] ?? [],
                'customer_owners' => $local['customer_owners'] ?? [],
                'date' => $local['title_date'] ?? null,
            ];
        }

        $title = trim((string) ($parsed['subject'] ?? ''));
        $predictor = new CategoryPredictor();
        $prediction = $predictor->predictWithCityOrgan($title) ?? [
            'categories' => [],
            'city' => null,
            'organ' => null,
        ];

        return response()->json([
            'data' => [
                'subject' => $parsed['subject'] ?? '',
                'description' => $parsed['description'] ?? $text,
                'summary' => $parsed['summary'] ?? '',
                'mokatebe' => $parsed['mokatebe'] ?? null,
                'kind' => $parsed['kind'] ?? 1,
                'date' => $this->normalizeDate($parsed['date'] ?? null),
                'organ_id' => $parsed['organ_id'] ?? ($prediction['organ'] ?? null),
                'organ_owner_ids' => $parsed['organ_owners'] ?? [],
                'customer_owner_ids' => $parsed['customer_owners'] ?? [],
                'city_id' => $prediction['city'] ?? null,
                'category_ids' => $prediction['categories'] ?? [],
                'city_name' => $prediction['city'] ? City::find($prediction['city'])?->name : null,
                'organ_name' => $prediction['organ'] ? Organ::find($prediction['organ'])?->name : null,
                'category_names' => Project::whereIn('id', $prediction['categories'] ?? [])->pluck('name')->values()->all(),
                'raw_text' => $text,
            ],
        ]);
    }

    private function normalizeDate($value): ?string
    {
        if (!$value) return null;
        $value = trim((string)$value);

        if (preg_match('/^(1[34]\d{2})[\/\-](\d{1,2})[\/\-](\d{1,2})$/u', strtr($value, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]), $m)) {
            try {
                return (new Jalalian((int)$m[1], (int)$m[2], (int)$m[3]))->toCarbon()->toIso8601String();
            } catch (\Throwable) {}
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function ocr(UploadedFile $file): string
    {
        $content = file_get_contents($file->getRealPath());
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $temp = app(\App\Services\TempFileService::class)->save($content, $ext);

        $url = url('/temp-download/' . $temp);

        $response = Http::timeout(90)->asForm()->post(
            'https://www.eboo.ir/api/ocr/getway',
            [
                'token' => env('EBOO_OCR_TOKEN'),
                'command' => 'addfile',
                'filelink' => $url,
            ]
        );

        $token = $response->json('FileToken');
        if (!$token) {
            throw new \RuntimeException('سرویس OCR توکن فایل را برنگرداند.');
        }

        $converted = Http::timeout(120)->asForm()->post(
            'https://www.eboo.ir/api/ocr/getway',
            [
                'token' => env('EBOO_OCR_TOKEN'),
                'command' => 'convert',
                'output' => 'txtraw',
                'filetoken' => $token,
                'method' => 4,
            ]
        );

        if (!$converted->successful()) {
            throw new \RuntimeException('سرویس OCR در تبدیل فایل خطا داد.');
        }

        return (string) $converted->body();
    }

    private function cleanMinuteText(string $text): string
    {
        try {
            $response = Http::timeout(90)->withHeaders([
                'Authorization' => 'Bearer ' . env('GAPGPT_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.gapgpt.app/v1/chat/completions', [
                'model' => 'gpt-4o',
                'temperature' => 0.1,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'متن OCR فارسی را اصلاح کن. خروجی فقط JSON معتبر با کلیدهای title و text باشد.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "متن OCR شده:\n\n{$text}\n\nخروجی JSON شامل عنوان مناسب و متن کامل اصلاح شده باشد.",
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
            ]);

            if (!$response->successful()) {
                return $text;
            }

            $content = $response->json('choices.0.message.content');
            $data = is_string($content) ? json_decode($content, true) : null;

            if (!is_array($data)) return $text;

            $title = trim((string) ($data['title'] ?? ''));
            $body = trim((string) ($data['text'] ?? ''));

            if ($title !== '') {
                return $title . ($body !== '' ? "\n" . $body : '');
            }

            return $body !== '' ? $body : $text;
        } catch (\Throwable $e) {
            Log::error('Mobile minute AI failed', ['message' => $e->getMessage()]);
            return $text;
        }
    }

    private function jalaliDateToIso(string $text): ?string
    {
        $digits = strtr($text, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);

        if (!preg_match('/\b(1[34]\d{2})[\/\-](\d{1,2})[\/\-](\d{1,2})\b/u', $digits, $m)) {
            return null;
        }

        try {
            return (new Jalalian((int)$m[1], (int)$m[2], (int)$m[3]))->toCarbon()->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function detectTaskName(string $title): ?string
    {
        $id = $this->detectTask($title);
        return $id ? Task::find($id)?->name : null;
    }

    private function namesForIds(array $ids): array
    {
        if (!$ids) return [];
        return \App\Models\Project::whereIn('id', $ids)->pluck('name')->values()->all();
    }

    private function detectTask(string $title): ?int
    {
        if ($title === '') return null;

        $predictor = new CategoryPredictor();
        $keywords = $predictor->extractKeywords($title);

        $best = null;
        $bestScore = 3;

        foreach (Task::query()->select(['id', 'name'])->latest('id')->limit(100)->get() as $task) {
            $taskKeywords = $predictor->extractKeywords($task->name);
            $score = count(array_intersect($taskKeywords, $keywords));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $task->id;
            }
        }

        return $best;
    }
}
