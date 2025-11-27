<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
                ['name' => 'دو شهرستان', 'parent_id' => null],
                ['name' => 'برخوار', 'parent_id' => null],
                ['name' => 'شهرستان شاهین شهر', 'parent_id' => null],
                ['name' => 'استان', 'parent_id' => null],
                ['name' => 'دولت آباد', 'parent_id' => 2],
                ['name' => 'شاهین شهر', 'parent_id' => 3],
                ['name' => 'کشور', 'parent_id' => null],
                ['name' => 'خورزوق', 'parent_id' => 2],
                ['name' => 'گز', 'parent_id' => 3],
                ['name' => 'گرگاب', 'parent_id' => 3],
                ['name' => 'حبیب آباد', 'parent_id' => 2],
                ['name' => 'میمه', 'parent_id' => 3],
                ['name' => 'دستگرد', 'parent_id' => 2],
                ['name' => 'محسن آباد', 'parent_id' => 2],
                ['name' => 'سین', 'parent_id' => 2],
                ['name' => 'کمشچه', 'parent_id' => 2],
                ['name' => 'شاپور آباد', 'parent_id' => 2],
                ['name' => 'علی آباد ملاعلی', 'parent_id' => 2],
                ['name' => 'وزوان', 'parent_id' => 3],
                ['name' => 'ازان', 'parent_id' => 3],
                ['name' => 'علی آبادچی', 'parent_id' => 2],
                ['name' => 'پروانه', 'parent_id' => 2],
                ['name' => 'مورچه خورت', 'parent_id' => 3],
                ['name' => 'موته', 'parent_id' => 3],
                ['name' => 'لایبید', 'parent_id' => 3],
                ['name' => 'حسن رباط', 'parent_id' => 3],
                ['name' => 'لوشاب', 'parent_id' => 3],
                ['name' => 'ونداده', 'parent_id' => 3],
                ['name' => 'زیاد آباد', 'parent_id' => 3],
                ['name' => 'خسروآباد', 'parent_id' => 3],
                ['name' => 'چغاده', 'parent_id' => 3],
                ['name' => 'مراوند', 'parent_id' => 3],
                ['name' => 'رباط آغاکمال', 'parent_id' => 3],
                ['name' => 'باغمیران', 'parent_id' => 3],
                ['name' => 'بخش میمه', 'parent_id' => 3],
                ['name' => 'بیدشک', 'parent_id' => 3],
                ['name' => 'جهاد آباد', 'parent_id' => 3],
                ['name' => 'دنبی', 'parent_id' => 2],
                ['name' => 'حوزه انتخابیه', 'parent_id' => null],
                ['name' => 'دهلر', 'parent_id' => 3],
                ['name' => 'سعید آباد', 'parent_id' => 3],
                ['name' => 'سه', 'parent_id' => 3],
                ['name' => 'شهرک صنعتی مورچه خورت', 'parent_id' => 3],
                ['name' => 'علی آبادچی و پروانه', 'parent_id' => 2],
                ['name' => 'قاسم آباد', 'parent_id' => 3],
                ['name' => 'کلهرود', 'parent_id' => 3],
                ['name' => 'محله اسلام آباد شهر خورزوق', 'parent_id' => 2],
                ['name' => 'محله امامزاده نرمی دولت آباد', 'parent_id' => 2],
                ['name' => 'محله دلیگان شهر خورزوق', 'parent_id' => 2],
                ['name' => 'محله سیمرغ شهر خورزوق', 'parent_id' => 2],
                ['name' => 'محله کربکند دولت اباد', 'parent_id' => 2],
                ['name' => 'محله لودریچه دولت آباد', 'parent_id' => 2],
                ['name' => 'مرغ', 'parent_id' => 2],
                ['name' => 'مرغ و دنبی', 'parent_id' => 2],
                ['name' => 'ناحیه صنعتی کمشچه', 'parent_id' => 2]
        ];

        foreach ($cities as $city) {
            DB::table('cities')->insert([
                'name' => $city
            ]);
        }
    }
}
