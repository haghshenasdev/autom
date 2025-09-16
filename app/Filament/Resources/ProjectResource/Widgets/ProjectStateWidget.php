<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStateWidget extends BaseWidget
{
    protected static ?int $sort = 0;
    public $record = null;
    protected function getStats(): array
    {
        return [
            Stat::make('کار های تعریف شده', $this->record->tasks()->count())->icon('heroicon-o-archive-box'),
            Stat::make('کار های انجام شده', $this->record->tasks()->where('completed','=',1)->count())->icon('heroicon-o-archive-box'),
            Stat::make('کار های مورد نیاز', $this->record->required_amount)->icon('heroicon-o-archive-box'),
        ];
    }
}
