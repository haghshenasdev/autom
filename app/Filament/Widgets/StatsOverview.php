<?php

namespace App\Filament\Widgets;

use App\Models\letter;
use App\Models\Minutes;
use App\Models\Project;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static bool $isLazy = true;

    protected static ?int $sort = 1;
    protected function getStats(): array
    {
        return [
            Stat::make('تعداد نامه ها', letter::query()->count())->icon('heroicon-o-envelope'),
            Stat::make('تعداد پروژه ها', Project::query()->count())->icon('heroicon-o-archive-box'),
            Stat::make('کار ها', Task::query()->count())->icon('heroicon-o-briefcase'),
            Stat::make('صورت جلسه ها', Minutes::query()->count())->icon('heroicon-o-document-text'),
        ];
    }
}
