<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('project_groups')->insert([
            ['name' => 'جلسه'],
            ['name' => 'کار'],
            ['name' => 'برنامه ریزی'],
            ['name' => 'دستور کار'],
        ]);

        DB::table('minutes_groups')->insert([
            ['id' => 1,'name' => 'هوش مصنوعی','parent_id'=> null],
        ]);

        DB::table('task_groups')->insert([
            ['id' => 1,'name' => 'جلسه','parent_id'=> null],
            ['id' => 2,'name' => 'کار','parent_id'=> null],
            ['id' => 3,  'name' => 'سفیران خدمت' , 'parent_id'=> 1],
            ['id' => 4,  'name' => 'همنشینی با  مردم', 'parent_id'=> 1],
            ['id' => 5,  'name' => 'ملاقات مردمی', 'parent_id'=> 2],
            ['id' => 6,  'name' => 'مراسمات', 'parent_id'=> 2],
            ['id' => 7,  'name' => 'بازدیدها', 'parent_id'=> 1],
            ['id' => 8,  'name' => 'دیدارها', 'parent_id'=> 2],
            ['id' => 9,  'name' => 'رسانه', 'parent_id'=> 2],
            ['id' => 10, 'name' => 'تذکرات کتبی', 'parent_id'=> 2],
            ['id' => 11, 'name' => 'کمیسیون ها', 'parent_id'=> 1],
            ['id' => 12, 'name' => 'فراکسیون ها', 'parent_id'=> 1],
            ['id' => 13, 'name' => 'دستور کار','parent_id'=> null],
            ['id' => 14, 'name' => 'سؤال', 'parent_id'=> 2],
            ['id' => 15, 'name' => 'نطق', 'parent_id'=> 2],
            ['id' => 16, 'name' => 'حضور در گلزار شهدا', 'parent_id'=> 2],
            ['id' => 17, 'name' => 'طرح  ها', 'parent_id'=> 2],
            ['id' => 18, 'name' => 'لوایح', 'parent_id'=> 2],
            ['id' => 19, 'name' => 'تحقیق و تفحص', 'parent_id'=> 2],
            ['id' => 20, 'name' => 'صحن علنی-اخطار قانون اساسی', 'parent_id'=> 2],
            ['id' => 21, 'name' => 'صحن علنی-رای اعتماد به وزرا', 'parent_id'=> 2],
            ['id' => 22, 'name' => 'استیضاح', 'parent_id'=> 2],
            ['id' => 23, 'name' => 'بیانیه', 'parent_id'=> 2],
            ['id' => 24, 'name' => 'اطلاع رسانی', 'parent_id'=> 2],
            ['id' => 25, 'name' => 'گروه مشورتی', 'parent_id'=> 1],
            ['id' => 26, 'name' => 'پیام ها', 'parent_id'=> 2],
            ['id' => 27, 'name' => 'تذکرات شفاهی', 'parent_id'=> 2],
            ['id' => 28, 'name' => 'بازدید از ادارات', 'parent_id'=> 1],
            ['id' => 29, 'name' => 'صحن علنی', 'parent_id'=> 1],
            ['id' => 30, 'name' => 'مطبوعات', 'parent_id'=> 2],
            ['id' => 31, 'name' => 'ماده 234 و ماده 45', 'parent_id'=> 2],
            ['id' => 32, 'name' => 'هوش مصنوعی', 'parent_id'=> null],
            ['id' => 33, 'name' => 'مصوبه', 'parent_id'=> null]
        ]);

        $this->call([
            CitySeeder::class,
            OrganSeeder::class,
            UserSeeder::class,
            DastorkarProjectSeeder::class,
            ProjectSeeder::class,
            ContentGroupSeeder::class,
        ]);
    }
}
