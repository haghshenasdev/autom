<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Http\Controllers\BaleBotController;

class SendBaleLoginNotification
{
    public function handle(Login $event): void
    {
        // فقط وقتی از طریق guard وب لاگین شده
        if ($event->guard == 'bot') {
            return;
        }

        $user = $event->user;

        // مشخصات سیستم (IP و User Agent)
        $ip = request()->ip();
        $agent = request()->header('User-Agent');

        $message = "🔐 سیستمی با مشخصات زیر به حساب شما دسترسی پیدا کرد و لاگین کرد:\n\n"
            . "🆔 آدرس آی پی: {$ip}\n"
            . "🌐 مرورگر: {$agent}";

        // ارسال پیام به ربات بله
        $bale_bot = new BaleBotController();
        $bale_bot->sendNotifBale($user->id, $message);
    }
}
