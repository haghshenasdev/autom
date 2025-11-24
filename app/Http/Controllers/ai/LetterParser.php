<?php

namespace App\Http\Controllers\ai;

use App\Models\Customer;
use App\Models\Letter;
use App\Models\Organ;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
                if (preg_match_all('/@\s*([^\s]+)/u', $text, $mentions)) {
                    if (!empty($mentions[1])) {
                        foreach ($mentions[1] as $m) {
                            $m = trim(str_replace('@', '', $m));
                            $us = User::where('name', 'like', "%$m%")
                                ->orWhere('id', $m)
                                ->first();
                            if ($us) $user[] = $us->id;
                        }
                    }
                }
                $user = array_unique($user);

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
                if (preg_match('/پیرو\s*مکاتبه\s+(\S+)/u', $text, $match)) {
                    $piroNumber = trim($match[1]);
                    if ($let = Letter::query()->where('mokatebe', $piroNumber)->first()) {
                        $piroNumber = $let->id;
                    } else {
                        $piroNumber = null;
                    }
                }

                $mokatebeNumber = null;
                if (preg_match('/نامه\s+(\S+)/u', $title, $matches)) {
                    $mokatebeNumber = $matches[1];
                    $title = str_replace($mokatebeNumber, '', $title);
                } elseif (preg_match('/مکاتبه\s+(\S+)/u', $text, $matches)) {
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

        $completionKeywords = ['#اتمام','#انجام', '#شد', '#انجام_شد'];
        $isCompletion = collect($completionKeywords)->contains(function ($kw) use ($text) {
            return mb_strpos($text, $kw) !== false;
        });

        $organ_owner = [];
        $customer_owner = [];
        $summary = '';
        $description = '';
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
                    $summary .= trim(substr($line, 1)) . "\n";
                } elseif (str_starts_with($line, 'هامش')) {
                    $summary .= trim(substr($line, strlen('هامش'))) . "\n";
                } elseif (str_starts_with($line, 'پاراف')) {
                    $summary .= trim(substr($line, strlen('پاراف'))) . "\n";
                } elseif (str_starts_with($line, 'نتیجه')) {
                    $summary .= trim(substr($line, strlen('نتیجه'))) . "\n";
                }elseif (str_starts_with($line, 'خلاصه')) {
                    $summary .= trim(substr($line, strlen('خلاصه'))) . "\n";
                }
            }elseif (str_starts_with($line, '-') ||
                str_starts_with($line, 'توضیح') ||
                str_starts_with($line, 'متن') ||
                str_starts_with($line, 'توضیحات')
            ) {

                if (str_starts_with($line, '-')) {
                    $description .= trim(substr($line, 1)) . "\n";
                } elseif (str_starts_with($line, 'توضیح')) {
                    $description .= trim(substr($line, strlen('توضیح'))) . "\n";
                } elseif (str_starts_with($line, 'متن')) {
                    $description .= trim(substr($line, strlen('متن'))) . "\n";
                } elseif (str_starts_with($line, 'توضیحات')) {
                    $description .= trim(substr($line, strlen('توضیحات'))) . "\n";
                }
            }
        }
        $organ_owner = array_unique($organ_owner);
        $customer_owner = array_unique($customer_owner);

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
            'description' => $description,
            'organ_owners' => $organ_owner,
            'customer_owners' => $customer_owner,
            'status' => $isCompletion ? 1 : 2,
        ];
    }

    public function aiParse(string $text)
    {

        // فراخوانی API هوش مصنوعی
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('GAPGPT_API_KEY'),
        ])->post('https://api.gapgpt.app/v1/chat/completions', [
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'user', 'content' => <<<EOT
لطفاً متن زیر را پردازش کن و اطلاعات نامه را استخراج کن.
خروجی را فقط در قالب JSON بده، بدون هیچ متن اضافی.

ساختار مورد انتظار:
/*
{
  "title": "موضوع نامه",
  "date": "YYYY/MM/DD",
  "description": "متن اصلی نامه",
  "mokatebe": "شماره مکاتبه عددی ، ممعمولا بالای متن هست و / یا - بینش دارد",
  "kind": "اگر نامه از طرف حسینعلی حاجی دلیگانی بود، 1 بزار در غیر این صورت 0",
  "pirow": "ببین اگر این نامه kind آن 0 بود ، شماره نامه ای که این نامه پیرو آن است را مقدار بده در غیر این صورت نال",
  "organ": "سمت گیرنده اصلی نامه ، اگر kind برابر 0 بود نال بزار",
  "refrals": ["سمت و نام رونوشت ها در صورت وجود"],
  "customer_owners": [" کد ملی صاحب های حقیقی نامه در صورت وجود"],
  "organ_owners": [" سمت یا نام های صاحب های حقوقی مثل شرکت یا ارگان نامه در صورت وجود"],
}
*/

متن:
{$text}
EOT],
            ],
        ]);

        $content = $response->json('choices.0.message.content');
        $content = str_replace(['```','json','\n'],'',$content);

        $dataLetter = json_decode($content, true);
//                    dd($dataLetter,$content);

        // --- پردازش ارگان ---
        $organId = null;
        if (!empty($dataLetter['organ'])) {
            $words = $this->extractKeywords($dataLetter['organ']);
            $organId = $this->detectOrgan($words);
        }

        // --- پردازش صاحب‌ها ---
        $customerIds = [];
        if (!empty($dataLetter['customer_owners'])) {
            foreach ($dataLetter['customer_owners'] as $ownerName) {
                $ownerName = trim($ownerName);
                if (!$ownerName) continue;

                $customer = Customer::query()->where('code_melli', $ownerName)->first();
                if ($customer) {
                    $customerIds[] = $customer->id;
                }
            }
        }
        $organIds = [];
        if (!empty($dataLetter['organ_owners'])) {
            foreach ($dataLetter['organ_owners'] as $ownerName) {
                $ownerName = trim($ownerName);
                $words = $this->extractKeywords($ownerName);
                $customer = $this->detectOrgan($words);

                if ($customer) {
                    $organIds[] = $customer;
                }
            }
        }

        // پر کردن فرم زیرین
        return[
            'subject'     => $dataLetter['title'] ?? null,
            'created_at'  => !empty($dataLetter['date']),
            'description' => $dataLetter['description'] ?? $text,
            'summary'     => implode("\n", $dataLetter['refrals'] ?? []),
            'mokatebe'    => $dataLetter['mokatebe'] ?? null,
            'daftar_id'   => null,
            'kind'        => $dataLetter['kind'] ?? 1,
            'organ_id'       => $organId,
            'organ_owners' => array_unique($organIds),
            'customer_owners' =>  array_unique($customerIds),
        ];
    }

    public function mixedParse(string $text): array
    {
        // جدا کردن بخش قبل و بعد از #متن
        $parts = explode('#متن', $text, 2);
        $beforeText = trim($parts[0]);
        $afterText = $parts[1] ?? null;

        // پردازش بخش قبل با تابع parse
        $parsedBefore = $this->parse($beforeText);

        // پردازش بخش بعد با تابع aiParse
        $parsedAfter = $afterText ? $this->aiParse($afterText) : [];

        // ترکیب نتایج با اولویت مقادیر parse
        $result = $parsedBefore;

        foreach ($parsedAfter as $key => $value) {
            if (empty($result[$key]) && !empty($value)) {
                $result[$key] = $value;
            }
        }

        // نگاشت کلیدها: title = subject ، title_date = created_at
        $result['title'] = $result['subject'] ?? null;
        $result['title_date'] = $result['created_at'] ?? null;

        return $result;
    }

    public function rebuildText(array $parsed): string
    {
        $lines = [];

        // عنوان و تاریخ
        if (!empty($parsed['title'])) {
            $line = '#نامه ';
            // نوع نامه
            if (isset($parsed['kind'])) {
                $line .= $parsed['kind'] == 1 ? 'به' : 'از';
                $line .= ' ';
            }
            $line .= $parsed['title'];
            if (!empty($parsed['title_date'])) {
                $line .= ' ' . $parsed['title_date'];
            }
            $lines[] = $line;
        }

        // شماره مکاتبه
        if (!empty($parsed['mokatebe'])) {
            $lines[] = 'مکاتبه ' . $parsed['mokatebe'];
        }

        // پیرو
        if (!empty($parsed['pirow'])) {
            $lines[] = 'پیرو ' . $parsed['pirow'];
        }


        // ارگان گیرنده
        if (!empty($parsed['organ_id'])) {
            $organ = Organ::find($parsed['organ_id']);
            if ($organ) {
                $lines[] = '@' . $organ->name;
            }
        }

        // صاحب‌های حقوقی
        if (!empty($parsed['organ_owners'])) {
            foreach ($parsed['organ_owners'] as $orgId) {
                $org = Organ::find($orgId);
                if ($org) {
                    $lines[] = 'صاحب ' . $org->name;
                }
            }
        }

        // صاحب‌های حقیقی
        if (!empty($parsed['customer_owners'])) {
            foreach ($parsed['customer_owners'] as $custId) {
                $cust = Customer::find($custId);
                if ($cust) {
                    $lines[] = 'صاحب شخص ' . $cust->name . ' ' . $cust->code_melli;
                }
            }
        }

        // خلاصه / هامش / پاراف
        if (!empty($parsed['summary'])) {
            foreach (explode("\n", trim($parsed['summary'])) as $s) {
                if ($s) $lines[] = '+ ' . $s;
            }
        }

        // توضیحات / متن
        if (!empty($parsed['description'])) {
            foreach (explode("\n", trim($parsed['description'])) as $d) {
                if ($d) $lines[] = '- ' . $d;
            }
        }

        // وضعیت
        if (isset($parsed['status'])) {
            if ($parsed['status'] == 1) {
                $lines[] = '#اتمام';
            }
        }

        return implode("\n", $lines);
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
