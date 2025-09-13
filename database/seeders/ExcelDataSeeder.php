<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\Jalalian;

class ExcelDataSeeder extends Seeder
{
    public function run()
    {
        $path = storage_path('app/excel/data.xlsx');

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
            "موته",
            "لایبید",
            "حسن رباط",
            "لوشاب",
            "ونداده",
            "زیاد آباد",
            "خسروآباد",
            "چغاده",
            "مراوند",
            "رباط آغاکمال",
        ];

        $rows = Excel::toArray([], $path)[0]; // فرض بر اینه که فقط یک شیت داریم

        foreach ($rows as $index => $row) {
            if ($index === 0) continue; // رد کردن ردیف عنوان‌ها

            $date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[1]);
            $time = Jalalian::fromDateTime($date)->toCarbon();
            $cityIndex = array_search(trim($row[2]), $cities);
            $cityId = $cityIndex !== false ? $cityIndex + 1 : null; // یا مقدار پیش‌فرض

            $task = \App\Models\Task::create([
                'name' => $row[0],
                'completed_at' => $time,
                'started_at' => $time,
                'description' => $row[3],
                'completed' => true,
                'created_by' => 1,
                'city_id' => $cityId,
                'status' => 1,
            ]);

            $selectedColumns = [];
            for ($i = 4; $i <= 20; $i++) {
                if (isset($row[$i]) && $row[$i] == 1) {
                    $selectedColumns[] = $i-3;
                }
            }
            $task->project()->attach($selectedColumns);
            $task->group()->attach(1);
        }
    }
}
