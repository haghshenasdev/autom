<?php

namespace App\Http\Controllers\ai;

use App\Models\Organ;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

class LetterParser
{
    public function parse(string $text): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $text)));

        $titleLine = array_shift($lines);
        $title = $this->cleanTitle($titleLine);
        $titleDate = $this->extractDateFromTitle($title);
        $words = $this->extractKeywords($title);
        $organ_ghirandeh = $this->detectOrgan($words);

        $user = null;
        if (preg_match_all('/@[\w_]+/u', $text, $mention)){
            $mention = trim(str_replace('@', '', $mention[0])[0]);
            $user = User::where('name', 'like', "%$mention%")
                ->orWhere('id', $mention)
                ->first();
            if ($user) $user = $user->id;
        }

        $kind = 1; // پیش فرض صادره
        if (str_contains($text,'وارده')){
            $kind = 2;
            str_replace('وارده', '' , $text);
        }

        $piroNumber = null;
        if (preg_match('/پیرو\s+(\d+)/u', $text, $matches)) {
            $piroNumber = $matches[1];
        }

        $daftar = 461; // دفتر تهران به صورت پیش فرض
        if (preg_match('/دفتر\s+(\S+)/u', $text, $matches)) {
            $afterDaftar = $matches[1];

            // استفاده در کوئری لاراول
            $organ = Organ::query()
                ->where('organ_type_id', 20)
                ->where('name', 'like', '%' . $afterDaftar . '%')
                ->first();
            if ($organ) $daftar = $organ->id;
        }

        return [
            'title' => $title,
            'title_date' => $titleDate,
            'organ_id' => $organ_ghirandeh,
            'user_id' => $user,
            'kind' => $kind,
            'pirow' => $piroNumber,
            'daftar' => $daftar,
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
                return (new Jalalian( $matches[1], $matches[2], $matches[3]))->toCarbon()->setTimeFrom(Carbon::now());
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    public function detectOrgan(array $keywords): ?int
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
        return $maxMatchCount > 0 ? $bestMatchId : null;
    }

    public function extractKeywords(string $text): array
    {

        // حذف علائم نگارشی و اعداد
        $text = preg_replace('/[^\p{L}\s]/u', '', $text);
        $text = preg_replace('/\d+/', '', $text);

        // تبدیل به آرایه کلمات و حذف stopwords
        $words = preg_split('/\s+/', $text);
        return $words;
    }
}
