<?php

namespace App\Http\Controllers\ai;

use App\Models\Customer;
use App\Models\Letter;
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
        $text = CalendarUtils::convertNumbers($text, true);
        $lines = array_filter(array_map('trim', explode("\n", $text)));

        $titleLine = array_shift($lines);
        $titleDate = $this->extractDateFromTitle($titleLine);
        $title = $this->cleanTitle($titleLine);
        $words = $this->extractKeywords($title);
        $organ_ghirandeh = $this->detectOrgan($words);

        $user = [];
        if (preg_match_all('/@[\w_]+/u', $text, $mentions)) {
            foreach ($mentions as $mention) {
                foreach ($mention as $m) {
                    $m = trim(str_replace('@', '', $m));
                    $us = User::where('name', 'like', "%$m%")
                        ->orWhere('id', $m)
                        ->first();
                    if ($us) $user[] = $us->id;
                }
            }
        }

        $kind = 1; // پیش فرض صادره
        if (preg_match('/نامه(?:\s+\d+)?\s+از/u', $title)) {
            $kind = 0;
        }

        $piroNumber = null;
        if (preg_match('/پیرو\s+(\d+)/u', $text, $matches)) {
            $piroNumber = $matches[1];
            if (!Letter::query()->find($piroNumber)) {
                $piroNumber = null;
            }
        }
        if (preg_match('/پیرو\s*مکاتبه\s+([^\n]+)/u', $text, $match)) {
            $piroNumber = trim($match[1]);
            if ($let = Letter::query()->where('mokatebe', $piroNumber)->first()) {
                $piroNumber = $let->id;
            } else {
                $piroNumber = null;
            }
        }

        $mokatebeNumber = null;
        if (preg_match('/نامه\s+(\d+)/u', $title, $matches)) {
            $mokatebeNumber = $matches[1];
            $title = str_replace($mokatebeNumber, '', $title);
        } elseif (preg_match('/مکاتبه\s+(\d+)/u', $text, $matches)) {
            $mokatebeNumber = $matches[1];
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

        $organ_owner = [];
        $customer_owner = [];
        $summary = '';
        foreach ($lines as $line) {
            $line = trim($line);

            // --- حالت صاحب یا = ---
            if (str_starts_with($line, '=') || str_starts_with($line, 'صاحب')) {
                // حذف کاراکتر یا کلمه کلیدی
                if (str_starts_with($line, '=')) {
                    $content = trim(substr($line, 1));
                } elseif (str_starts_with($line, 'صاحب')) {
                    $content = trim(substr($line, strlen('صاحب')));
                } else {
                    $content = $line;
                }

                // بررسی اینکه آیا با "شخص" شروع می‌شود
                if (str_starts_with($content, 'شخص')) {
                    $after = trim(substr($content, strlen('شخص')));

                    $parts = explode(' ', $after);
                    $nationalCode = null;
                    $name = '';

                    if (is_numeric(end($parts))) {
                        $nationalCode = array_pop($parts);
                        $name = implode(' ', $parts);
                    } else {
                        $name = $after;
                    }

                    if ($nationalCode) {
                        $customer = Customer::query()->where('code_melli', $nationalCode)->first();
                        if ($customer) {
                            $customer_owner[] = $customer->id;
                        } else {
                            $newCustomer = Customer::create([
                                'name' => $name ?: 'بدون نام',
                                'code_melli' => $nationalCode,
                            ]);
                            $customer_owner[] = $newCustomer->id;
                        }
                    } else {
                        $customer = Customer::query()->where('name', $name)->first();
                        if ($customer) {
                            $customer_owner[] = $customer->id;
                        } elseif ($name) {
                            $newCustomer = Customer::create([
                                'name' => $name,
                            ]);
                            $customer_owner[] = $newCustomer->id;
                        }
                    }
                } else {
                    $owner_keywords = $this->extractKeywords($content);
                    $organ_owner[] = $this->detectOrgan($owner_keywords);
                }
            } // --- حالت هامش یا پاراف یا + ---
            elseif (str_starts_with($line, '+') ||
                str_starts_with($line, 'هامش') ||
                str_starts_with($line, 'پاراف') ||
                str_starts_with($line, 'نتیجه') ||
                str_starts_with($line, 'خلاصه')
            ) {

                if (str_starts_with($line, '+')) {
                    $summary .= trim(substr($line, 1));
                } elseif (str_starts_with($line, 'هامش')) {
                    $summary .= trim(substr($line, strlen('هامش')));
                } elseif (str_starts_with($line, 'پاراف')) {
                    $summary .= trim(substr($line, strlen('پاراف')));
                } elseif (str_starts_with($line, 'نتیجه')) {
                    $summary .= trim(substr($line, strlen('نتیجه')));
                }elseif (str_starts_with($line, 'خلاصه')) {
                    $summary .= trim(substr($line, strlen('خلاصه')));
                }
            }
        }

        return [
            'title' => $title,
            'title_date' => $titleDate,
            'organ_id' => $organ_ghirandeh,
            'user_id' => $user,
            'kind' => $kind,
            'pirow' => $piroNumber,
            'mokatebe' => $mokatebeNumber,
            'daftar' => $daftar,
            'summary' => $summary,
            'organ_owners' => $organ_owner,
            'customer_owners' => $customer_owner,
        ];
    }

    protected function cleanTitle(string $line): string
    {
        $line = preg_replace('/\b\d{4}\/\d{1,2}\/\d{1,2}\b/u', '', $line);
        $line = str_replace('مورخ', '', $line);
        return ltrim($line, '# ');
    }

    protected function extractDateFromTitle(string $line): ?Carbon
    {
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $converted = str_replace($persianDigits, $englishDigits, $line);

        if (preg_match('/\b(\d{4})\/(\d{1,2})\/(\d{1,2})\b/u', $converted, $matches)) {
            try {
                return (new Jalalian($matches[1], $matches[2], $matches[3]))->toCarbon()->setTimeFrom(Carbon::now());
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
