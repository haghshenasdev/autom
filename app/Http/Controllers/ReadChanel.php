<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ai\CategoryPredictor;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Morilog\Jalali\Jalalian;

class ReadChanel extends Controller
{
    public function read()
    {
        $channel = \App\Models\ReadChanel::query()->findOrNew(1);
        $lastReadId = $channel->post_id ?? 0;

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
                $newPosts[$id] = $text;
            }

            // گرفتن کوچک‌ترین ID برای رفتن به عقب‌تر
            $currentId = array_key_first($result);

        } while ($foundNew);

        $newPosts = $this->removeExactDuplicateTitlesKeepHigherId($newPosts);

        // پردازش پست‌های جدید
        foreach ($newPosts as $id => $text) {
            $catPreder = new CategoryPredictor();
            $cats = $catPreder->predictWithCity($text[0]);

            $time = Carbon::parse($text[1]);
            if ($cats && $cats['categories']) {
                $task = Task::create([
                    'name' => $catPreder->cleanTitle($text[0]),
                    'created_at' => $time,
                    'completed_at' => $time,
                    'started_at' => $time,
                    'completed' => 1,
                    'status' => 1,
                    'Responsible_id' => 1,
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
            $channel->save();
        }
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
        $html = file_get_contents($url);

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
    }
}
