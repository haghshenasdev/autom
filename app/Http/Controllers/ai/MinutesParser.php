<?php

namespace App\Http\Controllers\ai;

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
        $organsName = [];

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
                $approve['due_at'] = $this->extractRelativeDate($rawLine);

                $approves[] = $approve;
            }else{
                // استخراج @های مستقل برای organs
                preg_match_all('/@[\w_]+/u', $line, $organMatchesLine);
                foreach ($organMatchesLine as $organMatches ){
                    foreach ($organMatches as $mention) {
                        $name = str_replace('_', ' ', $mention);
                        $name = str_replace('-', ' ', $name);
                        $name = str_replace('@', '', $name);
                        $organsName[] = $name;
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
            'organs_name' => $organsName,
        ];
    }

    protected function cleanTitle(string $line): string
    {
        return ltrim($line, '# ');
    }

    protected function extractDateFromTitle(string $line): ?string
    {
        $englishDigits = ['0','1','2','3','4','5','6','7','8','9'];
        $persianDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $converted = str_replace($persianDigits, $englishDigits, $line);

        if (preg_match('/\b(\d{4})\/(\d{1,2})\/(\d{1,2})\b/u', $converted, $matches)) {
            try {
                return $matches[0];
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    protected function extractRelativeDate(string $text): ?Carbon
    {
        $now = Jalalian::now();

        if (str_contains($text, 'تا یک روز')) {
            return $now->addDays(1)->toCarbon();
        } elseif (str_contains($text, 'تا یک هفته')) {
            return $now->addDays(7)->toCarbon();
        } elseif (str_contains($text, 'تا یک ماه')) {
            return $now->addMonths(1)->toCarbon();
        }

        return null;
    }
}
