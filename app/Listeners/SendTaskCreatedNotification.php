<?php

namespace App\Listeners;

use App\Events\TaskCreated;
use App\Filament\Resources\TaskResource;
use App\Http\Controllers\BaleBotController;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendTaskCreatedNotification
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
    public function handle(TaskCreated $event): void
    {
        $task = $event->task;

        if ($task->Responsible_id and $task->Responsible_id  != $task->created_by)
        {
            $bale_bot = new BaleBotController();
            $creator_name = $task->creator->name ?? 'نا مشخص';
            $message = "کار جدیدی توسط {$creator_name} برای شما ثبت شد: " . "\n";
            $message .= $bale_bot->CreateTaskMessage($task);
            $message .= "\n" . '[بازکردن در سامانه](' . TaskResource::getUrl('edit', [$task->id]) . ')' . "\n";
            $bale_bot->sendNotifBale($task->Responsible_id, $message);
            // ارسال به پنل سامانه
            $user = User::query()->find($task->Responsible_id);
            if ($user){
                Notification::make()
                    ->title('کار جدید')
                    ->body($message)
                    ->sendToDatabase($user);
            }

        }
    }
}
