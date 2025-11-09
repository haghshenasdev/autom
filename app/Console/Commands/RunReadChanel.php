<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ReadChanel;

class RunReadChanel extends Command
{
    protected $signature = 'run:controller';

    protected $description = 'خواندن کانال ایتا';

    public function handle()
    {
        $controller = new ReadChanel();
        $controller->read();
    }
}
