<?php

namespace App\Http\Controllers\ai;

use App\Models\City;
use Illuminate\Support\Facades\Cache;

class CategoryPredictor
{

    protected array $blacklist = [
        'تبریک','مبارک', 'تسلیت', 'صحن', 'ویدیو','فیلم','#فیلم', '#ویدیو', 'ببینید', '#ببینید','بازتاب','#پیشنهاد_تماشا', 'خبرگزاری', 'تصویر -','#پوشش_زنده', 'تسنیم', 'فارس', 'نامه','جلسه علنی'
    ];

    protected array $phrasesToRemove = [
        'نماینده مردم شریف شهرستانهای شاهین شهر و میمه و برخوار',
        'در مجلس شورای اسلامی',
        'کمیسیون اصل ۹۰',
        'کمیسیون اصل نود',
        'عصر امروز',
        'ظهر امروز',
        'صبح امروز',
        'هم اکنون',
        'انجام شد',
        'دقایقی قبل',
        'ساعاتی قبل',
        'ساعاتی پیش',
        'در حال برگزاری است',
        'در حال برگزاری',
        'برگزار شد',
        '@Hamase4',
        'جناب آقای حاجی',
        'حاجی',
        'جناب آقای حسینعلی',
        'حسینعلی',
        'دلیگانی',
    ];

    protected array $stopWords = [
        'از','به','در','با','برای','که','و','یا','تا','اما','اگر','این','آن','می','را','است','بود','شود',
        'کرد','کردن','نیز','هم','چون','بر','بین','یک','هیچ','همه','هر','چیزی','چند','چرا','چه','کجا','کی',
        'ما','شما','او','آنها','من','تو','ایشان','خود','همین','اکنون','امروز','فردا','دیروز'
    ];

    protected array $categoryKeywords = [
        2222 => ['سفر','معاون','وزیر','وزارت','کشور','مشاور','دعوت'], //'دعوت از مسئولان کشوری'
        2229 => ['حضور','جلسه','نشست','سخنرانی','استان','برنامه','مراسم','کلنگ','آئین'], //'حضور'
        2227 => ['بازدید','احداث','پروژه'], //'بازدید'
        2223 => ['مراسم','سخنرانی','حضور','نماز','مسجد'], //'حضور در مراسمات و نمازجعه و اجتماعات'
        2225 => ['دیدار','شهید','خانواده','امام'], //'دیدار'
        2221 => ['استان','اصفهان','مدیر','مدیرکل','مدیران','کل','جلسه','نشست'], //'جلسه با مدیران استانی'
        2224 => ['مسائل','جمعی','جلسه','نشست','اقشار','قشر','اصناف','صنف'], //'نشست با اقشار مختلف'
        2228 => ['جلسه','نشست','دیدار','گفتگو'], //'پذیرش و استماع مطالب دیگران'
        2220 => ['خانه','ملت'], //'خانه ملت'
        2236 => ['اعضای','یاران','معتمدان','نخبه','مشورت','مشورتی'], //'نشست گفتگوی یاران'
        2226 => ['مردمی','ملاقات'], //'ملاقات مردمی'
        2230 => ['پیگیری','ملت','مصوبه','مصوبات'], //'پیگیری جلسات خانه ملت'
        2232 => ['پیشرفت','معاونین','معاون','معاونان','جلسه'], //'جلسه با معاونین پیشرفت'
    ];

    public function predict(string $title, int $top = 2): ?array
    {
        if ($this->containsBlacklistedWord($title)) {
            return null;
        }

        $keywords = $this->extractKeywords($title);

        return $this->predictCore($keywords,$top);

    }

    public function predictWithCity(string $title, int $top = 2): ?array
    {
        if ($this->containsBlacklistedWord($title)) {
            return null;
        }

        $keywords = $this->extractKeywords($title);
        $topCategories = $this->predictCore($keywords, $top);
        $city_id = $this->detectCity($keywords);

        return [
            'categories' => array_keys($topCategories),
            'city' => $city_id
        ];
    }

    public function predictCore(array $keywords, int $top = 2): array
    {
        $scores = [];

        foreach ($this->categoryKeywords as $category => $words) {
            $score = 0;
            foreach ($keywords as $word) {
                if (in_array($word, $words)) {
                    $score++;
                }
            }
            if ($score > 0) {
                $scores[$category] = $score;
            }
        }

        arsort($scores);
        return array_slice($scores, 0, $top, true);
    }
    protected function extractKeywords(string $text): array
    {
        // حذف عبارات ناخواسته
        foreach ($this->phrasesToRemove as $phrase) {
            $text = str_replace($phrase, '', $text);
        }

        // حذف علائم نگارشی و اعداد
        $text = preg_replace('/[^\p{L}\s]/u', '', $text);
        $text = preg_replace('/\d+/', '', $text);

        // تبدیل به آرایه کلمات و حذف stopwords
        $words = preg_split('/\s+/', $text);
        return array_filter($words, fn($w) => !in_array($w, $this->stopWords) && mb_strlen($w) > 2);
    }

    public function detectCity(array $keywords): ?string
    {
        $cities = Cache::remember('city_records', 3600, function () {
            return City::select('id', 'name')->get();
        });

        $keywordSet = array_map('mb_strtolower', $keywords);

        foreach ($cities as $city) {
            $cityWords = preg_split('/\s+/', mb_strtolower($city->name));

            if (count(array_intersect($cityWords, $keywordSet)) === count($cityWords)) {
                return $city->id;
            }
        }

        return null;
    }

    private function containsBlacklistedWord(string $title)
    {
        // بررسی سریع بلک‌لیست بدون هیچ پردازشی
        foreach ($this->blacklist as $blocked) {
            if (mb_strpos($title, $blocked) !== false) {
                return true;
            }
        }
        return false;
    }

    public function cleanTitle(string $rawTitle): string
    {
        $text = preg_replace('/@\w+/', '', $rawTitle); // حذف آیدی‌ها مثل @Hamase4
        // حذف ایموجی‌ها، آیدی‌ها، و فاصله‌های اضافی
        $text =  preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);


        // حذف عبارات ناخواسته
        foreach ($this->phrasesToRemove as $phrase) {
            $text = str_replace($phrase, '', $text);
        }

        $text = preg_replace('/\s+/', ' ', $text); // حذف فاصله‌های اضافی

        return $text;
    }

}
