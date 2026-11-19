<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ai\CategoryPredictor;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Morilog\Jalali\Jalalian;
use Exception;

class ReadChanel extends Controller
{
    public function read()
    {
        try {
            $channel = \App\Models\ReadChanel::query()->findOrNew(1);
            $lastReadId = $channel->post_id ?? 0;

            $channel->timestamps = false;
            $channel->last_read_at = Carbon::now();
            $channel->save();
            $channel->timestamps = true;

            $currentId = null;
            $newPosts = [];

            do {
                $url = 'https://eitaa.com/Hamase4' . ($currentId ? "?before=$currentId" : '');
                $result = $this->dom($url);

                // مرتب‌سازی بر اساس ID برای اطمینان از ترتیب
                krsort($result);

                $foundNew = false;
                foreach ($result as $id => $text) {
                    if ((int) $id <= (int) $lastReadId) {
                        continue;
                    }

                    $foundNew = true;
                    $fullText = (string) ($text[0] ?? '');
                    $newPosts[$id] = [
                        $this->extractTaskTitle($fullText),
                        $text[1] ?? null,
                        $fullText,
                    ];
                }

                // گرفتن کوچک‌ترین ID برای رفتن به عقب‌تر
                $currentId = array_key_first($result);

            } while ($foundNew);

            $newPosts = $this->removeExactDuplicateTitlesKeepHigherId($newPosts);

            ksort($newPosts);
            $count = count($newPosts);
            echo $count . " پست خوانده شد .";
            // پردازش پست‌های جدید
            foreach ($newPosts as $id => $text) {
                $catPreder = new CategoryPredictor();
                $title = trim((string) ($text[0] ?? ''));
                $fullText = (string) ($text[2] ?? $title);
                $cats = $catPreder->predictWithCity($title);
                $time = Carbon::parse($text[1]);
                if ($title !== '' && $cats && $cats['categories']) {
                    $task = Task::create([
                        'name' => mb_substr($catPreder->cleanTitle($title), 0, 350),
                        'description' => $fullText,
                        'created_at' => $time,
                        'completed_at' => $time,
                        'started_at' => $time,
                        'completed' => 1,
                        'status' => 1,
                        'created_by' => 1,
                        'city_id' => $cats['city'],
                    ]);
                    $task->project()->attach($cats['categories']);
                    $task->group()->attach([1, 32]);
                }

                echo "Inserted ID: $id\n";
            }

            // ذخیره آخرین ID خوانده‌شده
            if (!empty($newPosts)) {
                $channel->post_id = max(array_keys($newPosts));
                $channel->last_count_read = $count;
                $channel->save();
            }
        }catch (Exception $exception){
            echo $exception->getMessage();
        }
    }

    /** Rank classifiable title candidates and prefer lines describing the actual event. */
    private function extractTaskTitle(string $content): string
    {
        $predictor = new CategoryPredictor();
        $lines = array_values(array_filter(array_map(
            static fn ($line) => trim(preg_replace('/^[^\p{L}\p{N}@]+/u', '', trim(preg_replace('/\s+/u', ' ', $line) ?? '')) ?? ''),
            preg_split('/\R/u', $content) ?: []
        ), static fn ($line) => $line !== ''));

        // اگر متن‌خوانی ایتا شکست خط را حذف کرده باشد، متن اصلی پس از دونقطه را جدا می‌کنیم.
        $lines = array_values(array_filter(array_map(static function ($line) {
            if (preg_match(
                '/^(?:با|به)\s+دعوت.*?(?:صورت\s+گرفت|انجام\s+شد|برگزار\s+شد)\s*[:：]\s*(.+)$/u',
                $line,
                $matches
            )) {
                return trim($matches[1]);
            }

            return $line;
        }, $lines), static fn ($line) => $line !== ''));

        // خبرهای کانال گاهی با یک مقدمه ثابت شروع می‌شوند؛ این عبارت مقدمه است، نه عنوان رویداد.
        $lines = array_values(array_filter($lines, static fn ($line) => !preg_match(
            '/^(?:با|به)\s+دعوت(?:\s|$).*(?:صورت\s+گرفت|انجام\s+شد|برگزار\s+شد)\s*[:：،؛.!؟]*$/u',
            $line
        )));

        $eventWords = ['بازدید', 'افتتاح', 'دیدار', 'بررسی', 'پیگیری', 'برگزاری', 'آغاز', 'بهره‌برداری', 'تأکید', 'تاکید', 'امضا', 'توافق'];
        $bestTitle = null;
        $bestScore = -1;

        foreach ($lines as $index => $line) {
            foreach ([1, 2, 3] as $span) {
                $candidate = trim(implode(' ', array_slice($lines, $index, $span)));
                if ($candidate === '') continue;

                // فقط عنوان‌های قابل دسته‌بندی نامزد می‌شوند؛ اما اولین تطبیق را فوراً انتخاب نمی‌کنیم.
                $categoryScores = $predictor->predict($candidate, 20);
                if (empty($categoryScores)) continue;

                $score = array_sum($categoryScores);
                foreach ($eventWords as $word) {
                    if (mb_strpos($candidate, $word) !== false) {
                        $score += $word === 'بازدید' ? 5 : 2;
                    }
                }

                // اگر امتیاز محتوایی برابر بود، عبارت کوتاه‌تر و متمرکزتر اولویت دارد.
                $score -= max(0, $span - 1) * 0.25;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestTitle = $candidate;
                }
            }
        }

        return $bestTitle ?? ($lines[0] ?? '');
    }

    function removeExactDuplicateTitlesKeepHigherId(array $posts): array
    {
        $seen = [];
        $result = [];

        foreach ($posts as $id => $data) {
            $title = $data[0];

            // اگر این عنوان قبلاً دیده نشده یا آیدی فعلی بزرگ‌تر از قبلیه، جایگزین کن
            if (!isset($seen[$title]) || $id > $seen[$title]) {
                $seen[$title] = $id;
            }
        }

        // حالا فقط آیتم‌هایی که آیدی‌شون در $seen هست رو نگه می‌داریم
        foreach ($seen as $title => $id) {
            $result[$id] = $posts[$id];
        }

        krsort($result); // مرتب‌سازی نزولی بر اساس آیدی (اختیاری)
        return $result;
    }



    public function dom(string $url)
    {
        try {
            /// تلاش در صورت شکست فراخوانی
            $attempts = 0;
            $maxAttempts = 5; //تعداد تلاش در هر شکست
            $waitSeconds = 3; // میزان صبر کردن بعد از هر شکست
            do {
                $html = @file_get_contents($url);
                if ($html !== false) {
                    break;
                }

                $attempts++;
                sleep($waitSeconds); // صبر قبل از تلاش مجدد
            } while ($attempts < $maxAttempts);

            if ($html === false) {
                throw new \Exception("خطا در دریافت محتوا از $url بعد از $maxAttempts تلاش.");
            }
            /////////////

// بارگذاری HTML در DOMDocument
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument();
            $dom->loadHTML($html);
            libxml_clear_errors();

// استفاده از XPath برای واکاوی تگ‌ها
            $xpath = new \DOMXPath($dom);

// پیدا کردن بخش اصلی پیام‌ها
            $section = $xpath->query('//section[contains(@class, "etme_channel_history")]')->item(0);
            if (!$section) {
                die("بخش پیام‌ها پیدا نشد.");
            }

// واکاوی divهای پیام
            $messages = $xpath->query('.//div[contains(@class, "etme_widget_message_wrap")]', $section);
            $result = [];
            foreach ($messages as $msg) {
                $id = $msg->getAttribute('id');
                $msg2 = $xpath->query('.//time[contains(@class, "time")]', $msg)[0];
                $msg = $xpath->query('.//div[contains(@class, "etme_widget_message_text")]', $msg)[0];
                $textContent = trim($msg->textContent);
                $result[$id] = [$textContent, $msg2->getAttribute('datetime')];
            }

            return $result;
        }catch (Exception $exception){
            echo $exception->getMessage();
            return [];
        }
    }
}
