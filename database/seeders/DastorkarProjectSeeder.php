<?php

namespace Database\Seeders;

use App\Models\Organ;
use App\Models\OrganType;
use App\Models\Project;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class DastorkarProjectSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('memory_limit', '512M');

        $path = storage_path('app/excel/دستور کار 1403هماهنگ با نرم افزار (2).xlsx');
        $Tinforows = Excel::toArray([], $path)[0];

        $usedIds = [];
        $uniqueRows = [];
        $duplicateRows = [];

        foreach ($Tinforows as $index => $row) {
            if ($index === 0) continue;

            $originalId = $row[0];

            if (!in_array($originalId, $usedIds)) {
                $usedIds[] = $originalId;
                $uniqueRows[] = $row;
            } else {
                $duplicateRows[] = $row;
            }
        }

// مرحله اول: ذخیره رکوردهای اصلی با آیدی واقعی
        foreach ($uniqueRows as $row) {
            $project = Project::create([
                'id' => $row[0],
                'city_id' => $row[1],
                'name' => $row[2],
                'organ_id' => $row[3],
                'user_id' => $row[4],
                'status' => $row[5],
                'description' => $row[6] . (($row[8] != null) ? "\n مقدار : " . $row[8] : ''),
                'amount' => $row[7],
            ]);
            $project->group()->attach(4);
        }

// مرحله دوم: ذخیره رکوردهای تکراری با آیدی جدید
        $lastId = Project::max('id');

        foreach ($duplicateRows as $row) {
            $lastId++;

            $project = Project::create([
                'id' => $lastId,
                'city_id' => $row[1],
                'name' => $row[0] . ' - ' . $row[2], // اضافه کردن آیدی اصلی به ابتدای نام
                'organ_id' => $row[3],
                'user_id' => $row[4],
                'status' => $row[5],
                'description' => $row[6] . (($row[8] != null) ? "\n مقدار : " . $row[8] : ''),
                'amount' => $row[7],
            ]);
            $project->group()->attach(4);
        }
    }
}
