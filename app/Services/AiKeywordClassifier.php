<?php

namespace App\Services;

use App\Models\AiWordsData;
use App\Models\City;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AiKeywordClassifier
{
    protected array $stopWords = [
        'از','به','در','با','برای','که','و','یا','تا','اما','اگر','این','آن','می','را','است','بود','شود',
        'کرد','کردن','نیز','هم','چون','بر','بین','یک','هیچ','همه','هر','چیزی','چند','چرا','چه','کجا','کی',
        'ما','شما','او','آنها','من','تو','ایشان','خود','همین','اکنون','امروز','فردا','دیروز','های','ها'
    ];

    protected array $phrasesToRemove = [
        'عنوان','موضوع','دستورکار','دسته','تسک',
        'نماینده مردم شریف شهرستانهای شاهین شهر و میمه و برخوار',
        'نماینده مردم شریف شهرستان های شاهین شهر و میمه و برخوار',
        'شاهین شهر و میمه و برخوار',
        'در مجلس شورای اسلامی',
        'کمیسیون اصل ۹۰',
        'کمیسیون اصل نود',
        'عصر امروز',
        'گزارش تصویری از',
        'گزارش تصویری',
        'بعد از ظهر امروز',
        'بعد از ظهر',
        'ظهر امروز',
        'صبح امروز',
        'هم اکنون',
        'انجام شد',
        'دقایقی قبل',
        'ساعاتی قبل',
        'ساعتی قبل',
        'ساعتی پیش',
        'ساعاتی پیش',
        'لحظاتی پیش',
        'در حال برگزاری است',
        'در حال برگزاری',
        'برگزار شد',
        '@Hamase4',
        'جناب آقای حاجی',
        'حسینعلی حاجی دليگانی',
        'حاجی',
        'جناب آقای حسینعلی',
        'حسینعلی',
        'دلیگانی',
    ];

    /**
     * استخراج کلمات کلیدی از متن
     */
    public function extractKeywords(string $text): array
    {
        foreach ($this->phrasesToRemove as $phrase) {
            $text = str_replace($phrase, '', $text);
        }

        $text = preg_replace('/[^\p{L}\s]/u', '', $text);
        $text = preg_replace('/\d+/', '', $text);

        $words = preg_split('/\s+/', $text);

        return array_values(array_filter($words, fn($w) =>
            !in_array($w, $this->stopWords) && mb_strlen($w) > 2
        ));
    }

    /**
     * تابع لرن: یادگیری از رکوردهای زیرمجموعه
     *
     * @param Model $parent رکورد دستورکار یا دسته‌بندی
     * @param string $relationName نام ریلیشن زیرمجموعه (مثلا letters یا tasks)
     * @param string $titleField فیلد عنوان زیرمجموعه
     * @param string|null $secondaryField فیلد ثانویه مثل شهر
     * @param float $sensitivityPercent حداقل درصد برای پذیرش مدل (مثلا 0.5 یعنی 50٪)
     * @return int تعداد کلمات وارد شده
     */
    public function learn(Model $parent, string $relationName, string $titleField, ?string $secondaryField = null, float $sensitivityPercent = 0.5): int
    {
        $keywords = [];
        $directWords = []; // کلمات مستقیم از نام مدل و فیلد ثانویه
        $totalSamples = $parent->$relationName->count();


        if ($totalSamples > 3){
            foreach ($parent->$relationName as $child) {
                $words = $this->extractKeywords($child->$titleField);

                foreach ($words as $w) {
                    $keywords[$w] = ($keywords[$w] ?? 0) + 1;
                }
            }
        }

        // افزودن کلمات عنوان
        $parentNameWords = $this->extractKeywords($parent->name);
        foreach ($parentNameWords as $w) {
            $keywords[$w] = ($keywords[$w] ?? 0) + 1;
            $directWords[] = $w; // این کلمات هم همیشه وارد لیست می‌شوند
        }
        $totalSamples = max(1, $totalSamples);


        // اضافه کردن فیلد ثانویه از مدل اصلی
        if ($secondaryField && !empty($parent->$secondaryField)) {
            $secondaryValue = $parent->$secondaryField;

            if ($secondaryValue instanceof Model) {
                $secondaryValue = $secondaryValue->name ?? (string)$secondaryValue;
            }

            $secondaryWords = $this->extractKeywords($secondaryValue);
            foreach ($secondaryWords as $w) {
                $keywords[$w] = ($keywords[$w] ?? 0) + 1;
                $directWords[] = $w; // این کلمات هم همیشه وارد لیست می‌شوند
            }
        }

        // مرتب‌سازی بر اساس بیشترین تکرار
        arsort($keywords);

        $allowedWords = $this->createAllowedWords($keywords,$totalSamples,$directWords,$sensitivityPercent);


        $aiWords = AiWordsData::firstOrNew([
            'model_type'   => get_class($parent),
            'model_id'     => $parent->id,
            'target_field' => $titleField,
        ]);

        $aiWords->allowed_words = $allowedWords;
        $aiWords->sensitivity   = $sensitivityPercent;
        $aiWords->save();

        return count($allowedWords);
    }


    /**
     * بهینه‌سازی کلمات: حذف کلمات مشترک در اکثر مدل‌ها
     *
     * @param string $modelType کلاس مدل (مثلا App\Models\Project)
     * @param array<int> $modelIds لیست آیدی‌های مدل
     * @param float $thresholdPercent درصد آستانه (مثلا 0.7 یعنی اگر کلمه در 70٪ مدل‌ها مشترک بود حذف شود)
     * @return int تعداد کلمات حذف‌شده
     */
    public function optimizeCommonWords(string $modelType, array $modelIds, float $thresholdPercent = 0.7): int
    {
        // پیدا کردن رکوردهای AiWordsData مربوط به این مدل و آیدی‌ها
        $datasets = AiWordsData::where('model_type', $modelType)
            ->whereIn('model_id', $modelIds)
            ->get();
        $totalDatasets = $datasets->count();

        if ($totalDatasets < 2) {
            return 0;
        }

        // شمارش حضور هر کلمه در چند مدل
        $wordPresence = [];
        foreach ($datasets as $data) {
            $words = collect($data->allowed_words)->pluck('word')->unique();
            foreach ($words as $w) {
                $wordPresence[$w] = ($wordPresence[$w] ?? 0) + 1;
            }
        }

        // گرفتن لیست اسامی شهرها از جدول cities
        $cityNames = City::pluck('name')->map(fn($n) => mb_strtolower(trim($n)))->toArray();

        // پیدا کردن کلمات مشترک (به جز اسامی شهرها)
        $commonWords = [];
        foreach ($wordPresence as $word => $count) {
            $percent = $count / $totalDatasets;
            if ($percent >= $thresholdPercent) {
                // اگر کلمه جزو اسامی شهرها نبود، در لیست حذف قرار گیرد
                if (!in_array(mb_strtolower(trim($word)), $cityNames)) {
                    $commonWords[] = $word;
                }
            }
        }

        // حذف کلمات مشترک از هر لیست
        $removed = 0;
        foreach ($datasets as $data) {
            $filtered = collect($data->allowed_words)
                ->reject(fn($rule) => in_array($rule['word'], $commonWords))
                ->values()
                ->toArray();

            $removed += count($data->allowed_words) - count($filtered);

            $data->allowed_words = $filtered;
            $data->save();
        }

        return $removed;
    }


    /**
     * تابع تشخیص دسته‌بندی/دستورکار بر اساس عنوان
     *
     * @param string $title عنوان ورودی
     * @param float $thresholdPercent حداقل درصد برای پذیرش مدل (مثلا 0.5 یعنی 50٪)
     * @param array|null $filterModelTypes آرایه‌ای از مدل‌تایپ‌هایی که باید بررسی شوند (مثلا ['App\Models\Project', 'App\Models\Task'])
     * @param Closure|null $queryCallback کوئری دلخواه روی AiWordsData (مثلا fn($q) => $q->where(...))
     * @param int|null $limitPerType حداکثر تعداد خروجی برای هر model_type
     *
     * @return array خروجی دسته‌بندی‌شده بر اساس model_type
     */
    public function classify(
        string $title,
        float $thresholdPercent = 0.5,
        ?array $filterModelTypes = null,
        ?\Closure $queryCallback = null,
        ?int $limitPerType = null
    ): array {
        $words = $this->extractKeywords($title);
        $results = [];

        // ساخت کوئری
        $query = AiWordsData::query();

        if ($filterModelTypes) {
            $query->whereIn('model_type', $filterModelTypes);
        }

        if ($queryCallback) {
            $queryCallback($query);
        }

        $datasets = $query->get();

        // بررسی هر رکورد
        foreach ($datasets as $data) {
            $score = 0;
            $matched = 0;
            $total = count($data->allowed_words ?? []);

            foreach ($data->allowed_words ?? [] as $rule) {
                $word = $rule['word'] ?? null;
                if (!$word) continue;

                if (in_array($word, $words) || count(array_intersect($rule['synonyms'] ?? [], $words)) > 0) {
                    $matched++;
                    $score += ($rule['required'] ?? false) ? 2 : 1;
                } else {
                    if ($rule['required'] ?? false) {
                        // اگر کلمه الزامی نبود، نتیجه رد می‌شود
                        $score = 0;
                        $matched = 0;
                        break;
                    }
                }
            }

            $percentMatch = $total > 0 ? ($matched / $total) : 0;

            // بررسی آستانه تطابق
            if ($percentMatch >= $thresholdPercent) {
                $results[] = [
                    'model_type' => $data->model_type,
                    'model_id'   => $data->model_id,
                    'score'      => $score,
                    'percent'    => round($percentMatch * 100, 2),
                ];
            }
        }

        // مرتب‌سازی کلی
        usort($results, function ($a, $b) {
            return $b['percent'] <=> $a['percent'] ?: $b['score'] <=> $a['score'];
        });

        // گروه‌بندی بر اساس مدل‌تایپ
        $grouped = [];

        foreach ($results as $res) {
            $grouped[$res['model_type']][] = [
                'model_id' => $res['model_id'],
                'score' => $res['score'],
                'percent' => $res['percent'],
            ];
        }

        // اعمال لیمیت برای هر مدل‌تایپ
        if ($limitPerType !== null) {
            foreach ($grouped as $modelType => $items) {
                $grouped[$modelType] = array_slice($items, 0, $limitPerType);
            }
        }

        return $grouped;
    }


    private function createAllowedWords($keywords,$totalSamples,$directWords,$sensitivityPercent): array
    {
        $allowedWords = [];
        foreach ($keywords as $word => $count) {
            $frequencyPercent = $count / $totalSamples;

            // اگر درصد حضور کافی بود یا کلمه مستقیم بود
            if (in_array($word, $directWords) or $frequencyPercent >= $sensitivityPercent) {
                if (!collect($allowedWords)->pluck('word')->contains($word)) {
                    $allowedWords[] = [
                        'word'       => $word,
                        'synonyms'   => [],
                        'required'   => false,
                        'order'      => null,
                        'frequency'  => $count,
                        'percent'    => round($frequencyPercent * 100, 2),
                    ];
                }
            }
        }
        return $allowedWords;
    }

}
