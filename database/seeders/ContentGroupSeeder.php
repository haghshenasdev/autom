<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\Jalalian;

class ContentGroupSeeder extends Seeder
{
    public function run()
    {
        $path = storage_path('app/excel/content_group.xlsx');

        $rows = Excel::toArray([], $path)[0]; // فرض بر اینه که فقط یک شیت داریم

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // رد کردن ردیف عنوان‌ها

            $task = \App\Models\ContentGroup::create([
                'id' => $row[0],
                'name' => $row[1],
                'parent_id' => $row[2],
            ]);
        }
    }
}
