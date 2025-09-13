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
        ]);

        DB::table('task_groups')->insert([
            ['name' => 'جلسه'],
            ['name' => 'کار'],
        ]);

        $this->call([
            CitySeeder::class,
            OrganSeeder::class,
            UserSeeder::class,
            ProjectSeeder::class,
        ]);
    }
}
