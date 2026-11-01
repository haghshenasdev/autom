<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Table::$defaultDateTimeDisplayFormat = 'Y/m/d H:i:s';

        // Forward application error-level logs to the configured private Bale admin chat.
        // The listener is deliberately best-effort: a Bale outage must never break the app.
        Log::listen(function (MessageLogged $event): void {
            if (!in_array(strtolower($event->level), ['error', 'critical', 'alert', 'emergency'], true)) {
                return;
            }

            $chatId = (string) config('bale.error_chat_id', env('BALE_ERROR_CHAT_ID', '1497344206'));
            $token = (string) config('bale.token');
            if ($chatId === '' || $token === '') {
                return;
            }

            try {
                $context = $event->context ?? [];
                unset($context['password'], $context['token'], $context['authorization']);
                $message = "🚨 خطای سامانه کارنما\n";
                $message .= "سطح: " . strtoupper($event->level) . "\n";
                $message .= "پیام: " . mb_substr((string) $event->message, 0, 1800);
                if (!empty($context)) {
                    $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
                    if ($encoded) $message .= "\nاطلاعات: " . mb_substr($encoded, 0, 1000);
                }

                Http::timeout(4)->post("https://tapi.bale.ai/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]);
            } catch (\Throwable $exception) {
                // Avoid logging here; that could recursively trigger this listener.
            }
        });
    }
}
