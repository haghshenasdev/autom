<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'محمد مهدی - معاون فناوری و نرم افزار', 'email' => 'm@m.ir'],
            ['name' => 'نماینده', 'email' => 'namayandeh@example.com'],
            ['name' => 'حمید حق شناس - مدیر', 'email' => 'haghshenas@example.com'],
            ['name' => 'قاسم شاه نظری', 'email' => 'nazari@example.com'],
            ['name' => 'جمشید - دبیر خانه', 'email' => 'jamshid@example.com'],
            ['name' => ' برخوار - طالبی', 'email' => 'taleby@example.com'],
            ['name' => 'شاهین شهر - طلبی', 'email' => 'talebi@example.com'],
            ['name' => 'روابط عمومی - صیدی', 'email' => 'seidi@example.com'],
            ['name' => 'معاون اجتماعی - علیرضا خیری', 'email' => 'kheiri@example.com'],
        ];

        foreach ($users as $user) {
            User::create([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => Hash::make('@123456789'), // رمز عبور پیش‌فرض
            ]);
        }
    }
}
