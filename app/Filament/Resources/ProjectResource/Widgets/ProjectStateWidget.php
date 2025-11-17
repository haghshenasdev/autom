<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStateWidget extends BaseWidget
{
    protected static ?int $sort = 0;
    public $record = null;

    public string|null $selectedYear = null;
    public array|null $betYear = null;



    protected function getStats(): array
    {
        return [
            Stat::make('کار های تعریف شده ' . $this->selectedYear,
                $this->betYear ? $this->record->tasks()->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->tasks()->count()
            )->icon('heroicon-o-archive-box'),
            Stat::make(' کار های انجام شده ' . $this->selectedYear,
                $this->betYear ?  $this->record->tasks()->where('completed','=',1)->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->tasks()->where('completed','=',1)->count()
            )->icon('heroicon-o-archive-box'),
            !$this->selectedYear ? Stat::make('کار های مورد نیاز', $this->record->required_amount)->icon('heroicon-o-archive-box') : null,
        ];
    }
}
