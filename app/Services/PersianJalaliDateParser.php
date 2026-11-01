<?php

namespace App\Services;

use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

class PersianJalaliDateParser
{
    private const MONTHS = [
        'فروردین' => 1, 'اردیبهشت' => 2, 'خرداد' => 3, 'تیر' => 4,
        'مرداد' => 5, 'شهریور' => 6, 'مهر' => 7, 'آبان' => 8,
        'آذر' => 9, 'دی' => 10, 'بهمن' => 11, 'اسفند' => 12,
    ];

    private const DAYS = [
        'اول' => 1, 'یکم' => 1, 'اولین' => 1,
        'دوم' => 2, 'سوم' => 3, 'چهارم' => 4, 'پنجم' => 5,
        'ششم' => 6, 'هفتم' => 7, 'هشتم' => 8, 'نهم' => 9,
        'دهم' => 10, 'یازدهم' => 11, 'دوازدهم' => 12, 'سیزدهم' => 13,
        'چهاردهم' => 14, 'پانزدهم' => 15, 'شانزدهم' => 16,
        'هفدهم' => 17, 'هجدهم' => 18, 'نوزدهم' => 19,
        'بیستم' => 20, 'بیست و یکم' => 21, 'بیست و دوم' => 22,
        'بیست و سوم' => 23, 'بیست و چهارم' => 24, 'بیست و پنجم' => 25,
        'بیست و ششم' => 26, 'بیست و هفتم' => 27, 'بیست و هشتم' => 28,
        'بیست و نهم' => 29, 'سی‌ام' => 30, 'سی ام' => 30,
        'سی و یکم' => 31,
    ];

    public function parse(string $text): ?Carbon
    {
        $text = trim(str_replace(['ي', 'ك', '‌'], ['ی', 'ک', ' '], $text));
        $digits = strtr($text, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);

        // Existing numeric Jalali forms: 1405/08/08, 1405-8-8.
        if (preg_match('/(?<!\d)(1[34]\d{2})[\/\-](\d{1,2})[\/\-](\d{1,2})(?!\d)/u', $digits, $m)) {
            return $this->makeDate((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        $monthNames = array_keys(self::MONTHS);
        $dayNames = array_keys(self::DAYS);
        usort($dayNames, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $monthPattern = implode('|', array_map(fn ($name) => preg_quote($name, '/'), $monthNames));
        $dayPattern = implode('|', array_map(fn ($name) => preg_quote($name, '/'), $dayNames));
        if (!preg_match('/(?:^|[^\p{L}\d])(?<day>\d{1,2}|'.$dayPattern.')\s+(?<month>'.$monthPattern.')\s*(?:ماه)?(?:\s+(?:سال\s*)?(?<year>1[34]\d{2}|جاری|امسال))?/u', $digits, $m)) {
            return null;
        }

        $dayText = trim($m['day']);
        $day = is_numeric($dayText) ? (int) $dayText : (self::DAYS[$dayText] ?? null);
        $month = self::MONTHS[$m['month']] ?? null;
        $yearText = trim((string) ($m['year'] ?? ''));
        $year = preg_match('/^1[34]\d{2}$/', $yearText)
            ? (int) $yearText
            : Jalalian::fromDateTime(Carbon::now())->getYear();

        if (!$day || !$month) return null;
        return $this->makeDate($year, $month, $day);
    }

    private function makeDate(int $year, int $month, int $day): ?Carbon
    {
        try {
            return (new Jalalian($year, $month, $day))->toCarbon()->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
