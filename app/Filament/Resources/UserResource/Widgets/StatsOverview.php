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
            Stat::make('فعالیت های تعریف شده ' . $this->selectedYear,
                $this->betYear ? $this->record->task_responsible()->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->task_responsible()->count()
            )->icon('heroicon-o-archive-box'),
            Stat::make(' فعالیت های انجام شده ' . $this->selectedYear,
                $this->betYear ?  $this->record->task_responsible()->where('completed','=',1)->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->task_responsible()->where('completed','=',1)->count()
            )->icon('heroicon-o-archive-box'),
            Stat::make(' فعالیت های انجام نشده ' . $this->selectedYear,
                $this->betYear ?  $this->record->task_responsible()->where(function ($q) {
                    $q->whereNull('completed')
                        ->orWhere('completed', 0);
                })->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->task_responsible()->where(function ($q) {
                    $q->whereNull('completed')
                        ->orWhere('completed', 0);
                })->count()
            )->icon('heroicon-o-archive-box'),

            Stat::make('کارپوشه (بررسی نشده/کل)' . $this->selectedYear, function () {
                $query = $this->record->cartable();
                if ($this->betYear) {
                    $query = $query->whereBetween('cartables.created_at', $this->betYear);
                }

                $total = $query->count();
                $unchecked = $query->where(function ($q) {
                    $q->whereNull('checked')->orWhere('checked', 0);
                })->count();

                return "{$unchecked} / {$total}";
            })->icon('heroicon-o-archive-box'),

            Stat::make('ارجاع‌ها (بررسی نشده/کل)' . $this->selectedYear, function () {
                $query = $this->record->referral();
                if ($this->betYear) {
                    $query = $query->whereBetween('referrals.created_at', $this->betYear);
                }

                $total = $query->count();
                $unchecked = $query->where(function ($q) {
                    $q->whereNull('checked')->orWhere('checked', 0);
                })->count();

                return "{$unchecked} / {$total}";
            })->icon('heroicon-o-archive-box'),

            Stat::make(' صورت جلسه ها ' . $this->selectedYear,
                $this->betYear ?  $this->record->minutes()->whereBetween('minutes.created_at', $this->betYear)->count() : $this->record->minutes()->count()
            )->icon('heroicon-o-archive-box'),
        ];
    }
}
