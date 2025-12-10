<?php

namespace App\Listeners;

use App\Events\AddedToCartable;
use App\Filament\Resources\LetterResource;
use App\Http\Controllers\BaleBotController;
use App\Models\Cartable;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendAddedToCartableNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(AddedToCartable $event): void
    {
            $cartable = $event->cartable;

            if (!$cartable) {
                $bale_bot = new BaleBotController();
                $na = $cartable->letter->user->name ?? 'نامشخص';
                $message = "یک نامه جدید توسط {$na} به کارتابل شما افزوده شد: " . "\n";
                $message .= $bale_bot->createCartableMessage($cartable);
                $bale_bot->sendNotifBale($cartable->user_id, $message .
                    '[بازکردن در سامانه](' . LetterResource::getUrl('edit', [$cartable->letter->id]) . ')' . "\n"
                );
                // ارسال به پنل سامانه
                $user = User::query()->find($cartable->user_id);
                if ($user){
                    Notification::make()
                        ->title('نامه جدید')
                        ->body($message)
                        ->sendToDatabase($user);
                }
            }
        }
}
