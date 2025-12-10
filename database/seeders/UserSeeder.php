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
            ['name' => 'سیستم', 'email' => 'm@m.ir'],
            ['name' => 'نماینده', 'email' => 'namayandeh@example.com'],
            ['name' => 'رئیس دفتر - حمید حق شناس', 'email' => 'haghshenas@example.com'],
            ['name' => 'مدیر دفتر - حمید حق شناس', 'email' => 'haghshenas2@example.com'],
            ['name' => 'مسئول دفتر تهران - معاونت پشتیبانی ، مالی و اداری - قاسم شاه نظری', 'email' => 'nazari@example.com'],
            ['name' => 'دبیر خانه - جمشید', 'email' => 'jamshid@example.com'],
            ['name' => 'معاونت پیشرفت برخوار - طالبی', 'email' => 'taleby@example.com'],
            ['name' => 'معاونت پیشرفت شاهین شهر - طلبی', 'email' => 'talebi@example.com'],
            ['name' => 'روابط عمومی - صیدی', 'email' => 'seidi@example.com'],
            ['name' => 'معاونت اجتماعی و معاونت خانه ملت ها - خیری', 'email' => 'kheiri@example.com'],
            ['name' => 'ستاد اختصاصی نماینده - خسروی', 'email' => 'khosravi@example.com'],
            ['name' => 'مسئول مراجعات - محمد حسین حق شناس', 'email' => 'mohammadhosein@example.com'],
            ['name' => 'سرمایه گذاری - بدون شخص', 'email' => 'sarmayeh@example.com'],
            ['name' => 'معاونت اشتغال و رفع موانع تولید - علینقی', 'email' => 'alinaghi@example.com'],
            ['name' => 'معاونت اصناف - محمد جواد خسروی', 'email' => 'mohammadjavad@example.com'],
            ['name' => 'معاونت اقشار و کمیسیون های اختصاصی - بدون شخص', 'email' => 'aghshar@example.com'],
            ['name' => 'معاونت امور مذهبی و روحانیت - خدارحمی', 'email' => 'khodarahmi@example.com'],
            ['name' => 'معاونت امور ورزشی - معاونت تسهیلات - احمدی', 'email' => 'ahmadi@example.com'],
            ['name' => 'معاونت امور بانوان - بدون شخص', 'email' => 'banovan@example.com'],
            ['name' => 'معاونت برنامه ریزی - رجبیان', 'email' => 'rajabian@example.com'],
            ['name' => 'معاونت تحلیل و آسیب شناسی و حقوقی - معاونت نظارت بر عملکرد - رضایی', 'email' => 'rezaie@example.com'],
            ['name' => 'معاونت تقنینی و پژوهشی - علی بذر افشان', 'email' => 'bazrafshan@example.com'],
            ['name' => 'محمد مهدی حق شناس - فناوری اطلاعات', 'email' => 'm2@m.ir'],
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
