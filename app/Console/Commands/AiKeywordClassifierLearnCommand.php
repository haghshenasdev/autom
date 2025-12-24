<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\AiKeywordClassifier;
use Illuminate\Console\Command;

class AiKeywordClassifierLearnCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:keyword-classifier-learn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'یاد گیری کلمات کلیدی';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ai = new AiKeywordClassifier();
        $this->info( "تداد کلمه تشخیص داده شده : " . $ai->learn(Project::query()->find(4),'tasks','name'));
    }
}
