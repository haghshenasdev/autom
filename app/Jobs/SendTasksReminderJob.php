<?php

namespace App\Jobs;

use App\Filament\Resources\TaskResource;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\User;
use App\Models\Task;
use App\Http\Controllers\BaleBotController;
use Carbon\Carbon;

class SendTasksReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    public function handle()
    {
        $today = \Carbon\Carbon::today();
        $threeDaysLater = Carbon::today()->addDays(3);

        // گرفتن همه کاربران
        $users = User::all();
        $bale_bot = new BaleBotController();
        foreach ($users as $user) {
            // تسک‌های کاربر که completed = false و ended_at <= امروز
            $tasks = Task::where('Responsible_id', $user->id)
                ->where(function ($q) {
                    $q->whereNull('completed')
                        ->orWhere('completed', 0);
                })
                ->where(function ($query) use ($today, $threeDaysLater) {
                    $query->whereDate('ended_at', '<=', $today) // گذشته
                    ->orWhereBetween('ended_at', [$today, $threeDaysLater]); // تا 3 روز آینده
                })
                ->orderByRaw("CASE
            WHEN DATE(ended_at) = ? THEN 0
            WHEN DATE(ended_at) < ? THEN 1
            ELSE 2 END", [$today, $today])
                ->orderBy('ended_at', 'asc')
                ->limit(10)
                ->get();

            if ($tasks->isEmpty()) {
                continue;
            }

            // ساخت پیام
            $message = "🌺 سلام صبح بخیر {$user->name} \n"
                . "🤗 امیدوارم روز خوبی داشته باشی\n\n"
                . "🗂 فعالیت های زیر برای شما در کارنما ثبت شده است و موعد انجام آن ها روبه اتمام است یا از موعد آن گذشته \n\n";

            foreach ($tasks as $task) {
                $delayDays = $today->diffInDays(Carbon::parse($task->ended_at), false);
//            $delayText = $delayDays < 0 ? abs($delayDays) . " روز تاخیر" : "امروز موعد انجام";

                if ($delayDays < 0) {
                    $delayText = abs($delayDays) . ' روز گذشته';
                } elseif ($delayDays === 0) {
                    $delayText = "امروز موعد انجام";
                } else {
                    $delayText = abs($delayDays) . ' روز مانده';
                }

                $message .= $bale_bot->CreateTaskMessage($task);
                $message .= "⌛ فرصت انجام : {$delayText}\n";
                $message .= "\n" . '[بازکردن در سامانه](' . TaskResource::getUrl('edit', [$task->id]) . ')' . "\n\n";
                $message .= "----------------------\n";
            }

            // ارسال پیام به ربات بله
            $bale_bot->sendNotifBale($user->id, $message);
            // ارسال به پنل سامانه
            Notification::make()
                ->title('یادآور فعالیت ها')
                ->body($message)
                ->sendToDatabase($user);
            $notification = $user->notifications()->latest()->first();
            if ($notification && ($notification->data['title'] ?? null) === 'یادآور فعالیت ها'
                && ($notification->data['body'] ?? null) === $message) {
                $notification->forceFill(['data' => array_merge($notification->data ?? [], [
                    'resource_type' => 'tasks',
                    'resource_id' => (int) $tasks->first()->id,
                    'resource_ids' => $tasks->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                ])])->save();
            }
        }
    }
}
