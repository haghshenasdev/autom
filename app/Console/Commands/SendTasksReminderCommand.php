<?php

namespace App\Console\Commands;

use App\Jobs\SendTasksReminderJob;
use Illuminate\Console\Command;

class SendTasksReminderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ارسال یادآوری تسک‌های کاربران';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SendTasksReminderJob::dispatch();
        $this->info('یادآوری تسک‌ها ارسال شد ✅');
    }
}
