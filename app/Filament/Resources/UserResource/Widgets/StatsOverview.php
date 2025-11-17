<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    public $record = null;

    public string|null $selectedYear = null;
    public array|null $betYear = null;

    protected function getStats(): array
    {
        return [
            Stat::make('کار های تعریف شده ' . $this->selectedYear,
                $this->betYear ? $this->record->task_responsible()->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->task_responsible()->count()
            )->icon('heroicon-o-archive-box'),
            Stat::make(' کار های انجام شده ' . $this->selectedYear,
                $this->betYear ?  $this->record->task_responsible()->where('completed','=',1)->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->task_responsible()->where('completed','=',1)->count()
            )->icon('heroicon-o-archive-box'),
            Stat::make(' کار های انجام نشده ' . $this->selectedYear,
                $this->betYear ?  $this->record->task_responsible()->where('completed','!=',1)->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->task_responsible()->where('completed','!=',1)->count()
            )->icon('heroicon-o-archive-box'),

            Stat::make('کارپوشه بررسی نشده ' . $this->selectedYear,
                $this->betYear ? $this->record->cartable()->where('checked','!=',1)->whereBetween('cartables.created_at', $this->betYear)->count() : $this->record->cartable()->where('checked','!=',1)->count()
            )->icon('heroicon-o-archive-box'),
            Stat::make(' ارجاع های بررسی نشده ' . $this->selectedYear,
                $this->betYear ?  $this->record->referral()->where('checked','!=',1)->whereBetween('referrals.created_at', $this->betYear)->count() : $this->record->referral()->where('checked','!=',1)->count()
            )->icon('heroicon-o-archive-box'),
            Stat::make(' صورت جلسه ها ' . $this->selectedYear,
                $this->betYear ?  $this->record->minutes()->whereBetween('minutes.created_at', $this->betYear)->count() : $this->record->minutes()->count()
            )->icon('heroicon-o-archive-box'),
        ];
    }
}
