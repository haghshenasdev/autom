<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ai\CategoryPredictor;
use App\Http\Controllers\ai\LetterParser;
use App\Http\Controllers\MinuteTextPS;
use App\Models\Task;
use App\Models\City;
use App\Models\Organ;
use App\Models\Project;
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
     * تحلیل عنوان فقط با داده‌ها و واژه‌نامه‌های دیتابیس؛ بدون فراخوانی سرویس هوش مصنوعی.
     */
    public function analyzeTitle(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:1000',
            'resource' => 'nullable|in:letters,minutes',
        ]);

        $title = trim($data['title']);
        $resource = $data['resource'] ?? 'letters';
        $requiredPermission = $resource === 'minutes' ? 'create_minutes' : 'create_letter';
        abort_unless($request->user() && $request->user()->can($requiredPermission), 403, 'اجازه تحلیل عنوان برای این فرم را ندارید.');

        $canViewProjects = $request->user()->can('view_any_project');
        $canViewTasks = $request->user()->can('view_any_task');
        $predictor = new CategoryPredictor();
        $keywords = array_values(array_unique($predictor->extractKeywords($title)));

        // در این مسیر عمداً از predictWithCityOrgan استفاده نمی‌کنیم، چون فهرست
        // واژه‌های مستثنا ممکن است خود عنوان نامه/جلسه را به‌طور کامل رد کند.
        $scores = $predictor->predictCore($keywords, 5);
        $categoryIds = $canViewProjects
            ? array_values(array_map('intval', array_keys($scores)))
            : [];
        $cityId = $predictor->detectCity($keywords);
        $organId = $predictor->detectOrgan($keywords);

        $projects = Project::query()
            ->whereIn('id', $categoryIds)
            ->get(['id', 'name'])
            ->map(fn ($item) => ['id' => (int) $item->id, 'name' => (string) $item->name])
            ->values();

        // پیشنهادهای متنی از پروژه‌ها/دستورکارها؛ فقط پیشنهاد هستند و خودکار
        // به رکورد متصل نمی‌شوند تا انتخاب نهایی دست کاربر بماند.
        $projectQuery = Project::query();
        $taskQuery = Task::query();
        if (!$canViewProjects) $projectQuery->whereRaw('1 = 0');
        if (!$canViewTasks) $taskQuery->whereRaw('1 = 0');
        if ($keywords && $canViewProjects) {
            $terms = array_slice($keywords, 0, 6);
            $projectQuery->where(function ($query) use ($terms) {
                foreach ($terms as $term) $query->orWhere('name', 'like', '%' . $term . '%');
            });
        }
        if ($keywords && $canViewTasks) {
            $terms = array_slice($keywords, 0, 6);
            $taskQuery->where(function ($query) use ($terms) {
                foreach ($terms as $term) $query->orWhere('name', 'like', '%' . $term . '%');
            });
        } else {
            $taskQuery->whereRaw('1 = 0');
        }
        if (!$keywords || !$canViewProjects) $projectQuery->whereRaw('1 = 0');

        $suggestedProjects = $projectQuery->select('id', 'name')->limit(8)->get()
            ->map(fn ($item) => ['id' => (int) $item->id, 'name' => (string) $item->name])
            ->values();
        $suggestedTasks = $taskQuery->select('id', 'name')->latest('id')->limit(8)->get()
            ->map(fn ($item) => ['id' => (int) $item->id, 'name' => (string) $item->name])
            ->values();

        // ابتدا دسته‌بندی‌های پیش‌بینی‌شده و سپس پیشنهادهای مشابه را ادغام می‌کنیم.
        $projects = $projects->concat($suggestedProjects)->unique('id')->take(8)->values();

        return response()->json(['data' => [
            'title' => $title,
            'city_id' => $cityId ? (int) $cityId : null,
            'city_name' => $cityId ? City::find($cityId)?->name : null,
            'organ_id' => $organId ? (int) $organId : null,
            'organ_name' => $organId ? Organ::find($organId)?->name : null,
            'category_ids' => $categoryIds,
            'category_names' => $this->namesForIds($categoryIds),
            'projects' => $projects,
            'tasks' => $suggestedTasks,
        ]]);
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
                return response()->json(['message' => 'استخراج متن از فایل انجام نشد: ' . $e->getMessage()], 422);
            }
        }

        if ($text === '') {
            return response()->json(['message' => 'متن یا فایل برای تحلیل ارسال نشده است.'], 422);
        }

        $cleaned = $this->cleanMinuteText($text);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $cleaned))));
        $title = $lines[0] ?? '';

        $predictor = new CategoryPredictor();
        $keywords = array_values(array_unique($predictor->extractKeywords($title)));
        // عنوان‌های دارای واژه‌های عمومی مانند «جلسه» نیز باید تحلیل شوند؛
        // فهرست blacklist برای این مسیر اعمال نمی‌شود.
        $prediction = [
            'categories' => array_map('intval', array_keys($predictor->predictCore($keywords, 5))),
            'city' => $predictor->detectCity($keywords),
            'organ' => $predictor->detectOrgan($keywords),
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
                'category_names' => $this->namesForIds($prediction['categories'] ?? []),
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
                return response()->json(['message' => 'استخراج متن از فایل انجام نشد: ' . $e->getMessage()], 422);
            }
        }

        if ($text === '') {
            return response()->json(['message' => 'متن یا فایل برای تحلیل ارسال نشده است.'], 422);
        }

        $parser = new LetterParser();
        $parsed = null;

        // اگر کلید سرویس هوش مصنوعی تنظیم شده باشد ابتدا تحلیل قبلی را امتحان
        // می‌کنیم؛ در صورت خطا یا خروجی ناقص، parser محلی/قواعدی اجرا می‌شود.
        if (trim((string) env('GAPGPT_API_KEY')) !== '') {
            try {
                $parsed = $parser->aiParse($text);
            } catch (\Throwable $e) {
                Log::warning('Mobile letter AI unavailable; using local parser', ['message' => $e->getMessage()]);
            }
        }

        if (!is_array($parsed) || trim((string) ($parsed['subject'] ?? $parsed['title'] ?? '')) === '') {
            try {
                $parsed = $parser->parse($text);
            } catch (\Throwable $e) {
                Log::error('Mobile letter local parser failed', ['message' => $e->getMessage()]);
                // حداقل عنوان و متن را از ورودی نگه می‌داریم تا ثبت فرم متوقف نشود.
                $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text))));
                $parsed = ['title' => $lines[0] ?? '', 'description' => $text];
            }
        }

        $title = trim((string) ($parsed['subject'] ?? $parsed['title'] ?? ''));
        $predictor = new CategoryPredictor();
        $keywords = array_values(array_unique($predictor->extractKeywords($title)));
        $scores = $predictor->predictCore($keywords, 5);
        $categoryIds = array_values(array_map('intval', array_keys($scores)));
        $cityId = $predictor->detectCity($keywords);
        $predictedOrganId = $predictor->detectOrgan($keywords);

        return response()->json([
            'data' => [
                'subject' => $title,
                'description' => $parsed['description'] ?? $text,
                'summary' => $parsed['summary'] ?? '',
                'mokatebe' => $parsed['mokatebe'] ?? null,
                'kind' => $parsed['kind'] ?? 1,
                'date' => $this->normalizeDate($parsed['date'] ?? $parsed['title_date'] ?? null),
                'organ_id' => $parsed['organ_id'] ?? $predictedOrganId,
                'organ_owner_ids' => array_values(array_filter($parsed['organ_owners'] ?? [], fn ($id) => is_numeric($id))),
                'customer_owner_ids' => array_values(array_filter($parsed['customer_owners'] ?? [], fn ($id) => is_numeric($id))),
                'city_id' => $cityId ? (int) $cityId : null,
                'category_ids' => $categoryIds,
                'city_name' => $cityId ? City::find($cityId)?->name : null,
                'organ_name' => ($parsed['organ_id'] ?? $predictedOrganId) ? Organ::find($parsed['organ_id'] ?? $predictedOrganId)?->name : null,
                'category_names' => $this->namesForIds($categoryIds),
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
        $ocrToken = trim((string) env('EBOO_OCR_TOKEN'));
        if ($ocrToken === '') {
            throw new \RuntimeException('کلید EBOO_OCR_TOKEN در تنظیمات سرور تعریف نشده است.');
        }

        $content = file_get_contents($file->getRealPath());
        if ($content === false || $content === '') {
            throw new \RuntimeException('محتوای فایل ارسالی قابل خواندن نیست.');
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $temp = app(\App\Services\TempFileService::class)->save($content, $ext);
        $url = url('/temp-download/' . $temp);

        $response = Http::timeout(90)->asForm()->post(
            'https://www.eboo.ir/api/ocr/getway',
            ['token' => $ocrToken, 'command' => 'addfile', 'filelink' => $url]
        );

        if (!$response->successful()) {
            Log::warning('OCR addfile request failed', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            throw new \RuntimeException('سرویس OCR فایل را نپذیرفت؛ آدرس عمومی فایل و دسترسی سرویس را بررسی کنید.');
        }

        $token = $response->json('FileToken') ?? $response->json('filetoken');
        if (!$token) {
            Log::warning('OCR addfile response did not include FileToken', ['body' => mb_substr($response->body(), 0, 500)]);
            throw new \RuntimeException('سرویس OCR توکن فایل را برنگرداند؛ کلید OCR یا دسترسی فایل را بررسی کنید.');
        }

        $converted = Http::timeout(120)->asForm()->post(
            'https://www.eboo.ir/api/ocr/getway',
            ['token' => $ocrToken, 'command' => 'convert', 'output' => 'txtraw', 'filetoken' => $token, 'method' => 4]
        );

        if (!$converted->successful()) {
            Log::warning('OCR conversion failed', ['status' => $converted->status(), 'body' => mb_substr($converted->body(), 0, 500)]);
            throw new \RuntimeException('سرویس OCR در تبدیل فایل خطا داد.');
        }

        $text = trim((string) $converted->body());
        if ($text === '') {
            throw new \RuntimeException('سرویس OCR متنی از فایل استخراج نکرد.');
        }
        return $text;
    }

    private function cleanMinuteText(string $text): string
    {
        if (trim((string) env('GAPGPT_API_KEY')) === '') {
            return $text;
        }

        try {
            $response = Http::timeout(25)->withHeaders([
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
