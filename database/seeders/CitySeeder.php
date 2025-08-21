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
            "دو شهرستان",
            "برخوار",
            "شهرستان شاهین شهر",
            "استان",
            "دولت آباد",
            "شاهین شهر",
            "کشور",
            "خورزوق",
            "گز",
            "گرگاب",
            "حبیب آباد",
            "میمه",
            "دستگرد",
            "محسن آباد",
            "سین",
            "کمشچه",
            "شاپور آباد",
            "علی آباد ملاعلی",
            "وزوان",
            "ازان",
            "علی آبادچی",
            "پروانه",
            "مورچه خورت",
            "مورچه خورت",
        ];

        foreach ($cities as $city) {
            DB::table('cities')->insert([
                'name' => $city
            ]);
        }
    }
}
