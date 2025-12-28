<?php

namespace App\Services;

use App\Models\AiWordsData;
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
        'عنوان','موضوع','پروژه','دسته','تسک',
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
     * @param Model $parent رکورد پروژه یا دسته‌بندی
     * @param string $relationName نام ریلیشن زیرمجموعه (مثلا letters یا tasks)
     * @param string $titleField فیلد عنوان زیرمجموعه
     * @param string|null $secondaryField فیلد ثانویه مثل شهر
     * @param float $sensitivityPercent حداقل درصد برای پذیرش مدل (مثلا 0.5 یعنی 50٪)
     * @return int تعداد کلمات وارد شده
     */
    public function learn(Model $parent, string $relationName, string $titleField, ?string $secondaryField = null, float $sensitivityPercent = 0.5): int
    {
        $keywords = [];
        $totalSamples = count($parent->$relationName);

        // اگر تعداد زیرمجموعه‌ها کمتر از 3 بود، از name خود مدل استفاده کن
        if ($totalSamples < 3) {
            $words = $this->extractKeywords($parent->name);

            foreach ($words as $w) {
                $keywords[$w] = ($keywords[$w] ?? 0) + 1;
            }

            $totalSamples = max(1, $totalSamples); // جلوگیری از تقسیم بر صفر
        } else {
            foreach ($parent->$relationName as $child) {
                $words = $this->extractKeywords($child->$titleField);

                foreach ($words as $w) {
                    $keywords[$w] = ($keywords[$w] ?? 0) + 1;
                }
            }
        }

        // اضافه کردن فیلد ثانویه از مدل اصلی
        if ($secondaryField && !empty($parent->$secondaryField)) {
            $secondaryValue = $parent->$secondaryField;

            // اگر فیلد Foreign Key بود و رابطه دارد، مقدار نیمه ارتباطش خوانده شود
            if ($secondaryValue instanceof Model) {
                // فرض می‌کنیم فیلد اصلی رابطه name است
                $secondaryValue = $secondaryValue->name ?? (string)$secondaryValue;
            }

            $secondaryWords = $this->extractKeywords($secondaryValue);
            foreach ($secondaryWords as $w) {
                $keywords[$w] = ($keywords[$w] ?? 0) + 1;
            }
        }

        // مرتب‌سازی بر اساس بیشترین تکرار
        arsort($keywords);

        $allowedWords = [];
        foreach ($keywords as $word => $count) {
            $frequencyPercent = $count / $totalSamples;

            if ($frequencyPercent >= $sensitivityPercent) {
                // جلوگیری از ورود کلمات تکراری
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

        if ($totalDatasets === 0) {
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

        // پیدا کردن کلمات مشترک
        $commonWords = [];
        foreach ($wordPresence as $word => $count) {
            $percent = $count / $totalDatasets;
            if ($percent >= $thresholdPercent) {
                $commonWords[] = $word;
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
     * تابع تشخیص دسته‌بندی/پروژه بر اساس عنوان
     *
     * @param string $title
     * @param float $thresholdPercent حداقل درصد برای پذیرش مدل (مثلا 0.5 یعنی 50٪)
     * @return array لیست دسته‌بندی‌ها/پروژه‌های مرتبط
     */
    public function classify(string $title, float $thresholdPercent = 0.5): array
    {
        $words = $this->extractKeywords($title);
        $results = [];

        $datasets = AiWordsData::all();
        foreach ($datasets as $data) {
            $score = 0;
            $matched = 0;
            $total   = count($data->allowed_words);

            foreach ($data->allowed_words as $rule) {
                $word = $rule['word'];

                // بررسی وجود کلمه یا مترادف‌ها
                if (in_array($word, $words) || count(array_intersect($rule['synonyms'], $words)) > 0) {
                    $matched++;

                    // اگر کلمه ضروری باشد و وجود داشته باشد، امتیاز بیشتری بده
                    if ($rule['required']) {
                        $score += 2;
                    } else {
                        $score += 1;
                    }
                } else {
                    // اگر کلمه ضروری باشد و وجود نداشته باشد، کل مدل رد شود
                    if ($rule['required']) {
                        $score = 0;
                        $matched = 0;
                        break;
                    }
                }
            }

            // محاسبه درصد تطابق
            $percentMatch = $total > 0 ? ($matched / $total) : 0;

            // فقط اگر درصد تطابق >= آستانه باشد، مدل پذیرفته شود
            if ($percentMatch >= $thresholdPercent) {
                $results[] = [
                    'model_type' => $data->model_type,
                    'model_id'   => $data->model_id,
                    'score'      => $score,
                    'percent'    => round($percentMatch * 100, 2),
                ];
            }
        }

        // مرتب‌سازی بر اساس درصد و امتیاز
        usort($results, function ($a, $b) {
            return $b['percent'] <=> $a['percent'] ?: $b['score'] <=> $a['score'];
        });

        return $results;
    }

}
