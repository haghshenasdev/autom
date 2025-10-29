<?php

namespace App\Http\Controllers\ai;

use App\Models\User;
use App\Models\Organ;
use Morilog\Jalali\Jalalian;
use Carbon\Carbon;

class MinutesParser
{
    public function parse(string $text): array
    {
        $lines = array_filter(array_map('trim', explode("\n", $text)));

        $titleLine = array_shift($lines);
        $title = $this->cleanTitle($titleLine);
        $titleDate = $this->extractDateFromTitle($titleLine);

        $approves = [];
        $organs = [];

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
            }

            // استخراج @های مستقل برای organs
            preg_match_all('/@([\w_]+)/u', $line, $organMatches);

            foreach ($organMatches[1] as $mention) {
                $name = str_replace('_', ' ', $mention);
                $organ = Organ::where('name', 'like', "%$name%")
                    ->orWhere('id', $mention)
                    ->first();
                if ($organ) {
                    $organs[] = ['id' => $organ->id, 'name' => $organ->name];
                }
            }
        }

        return [
            'title' => $title,
            'title_date' => $titleDate,
            'approves' => $approves,
            'organs' => $organs,
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

        if (preg_match('/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $converted, $matches)) {
            try {
                return Jalalian::fromFormat('Y/m/d', "{$matches[1]}/{$matches[2]}/{$matches[3]}")->toCarbon();
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
