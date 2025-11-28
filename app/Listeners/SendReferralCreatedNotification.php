<?php

namespace App\Listeners;

use App\Events\NewReferral;
use App\Filament\Resources\LetterResource;
use App\Http\Controllers\BaleBotController;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendReferralCreatedNotification
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
    public function handle(NewReferral $event): void
    {
        $referral = $event->referral;


        $bale_bot = new BaleBotController();
        $message = "یک ارجاع جدید توسط {$referral->by_users->name} برای شما ثبت شد: " . "\n";
        $message .= $bale_bot->CreateReferralMessage($referral);
        $bale_bot->sendNotifBale($referral->to_user_id, $message .
        '[بازکردن در سامانه](' . LetterResource::getUrl('edit', [$referral->letter->id]) . ')' . "\n"
        );
        // ارسال به پنل سامانه
        $user = User::query()->find($referral->to_user_id);
        if ($user){
            Notification::make()
                ->title('ارجاع جدید')
                ->body($message)
                ->sendToDatabase($user);
        }


    }
}
