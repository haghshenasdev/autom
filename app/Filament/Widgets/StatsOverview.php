<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
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
            Stat::make('تعداد نامه ها', $this->formatShortNumber(letter::query()->count()))->icon('heroicon-o-envelope'),
            Stat::make('مراجعه کننده ها', $this->formatShortNumber(Customer::query()->count()))->icon('heroicon-o-user'),
            Stat::make('تعداد پروژه ها', $this->formatShortNumber(Project::query()->count()))->icon('heroicon-o-archive-box'),
            Stat::make('کار یا جلسه', $this->formatShortNumber(Task::query()->count()))->icon('heroicon-o-briefcase'),
            Stat::make('صورت جلسه ها', $this->formatShortNumber(Minutes::query()->count()))->icon('heroicon-o-document-text'),
        ];
    }

    private function formatShortNumber($number)
    {
        if ($number >= 1000000000) {
            return round($number / 1000000000, 1) . 'B';
        } elseif ($number >= 1000000) {
            return round($number / 1000000, 1) . 'M';
        } elseif ($number >= 1000) {
            return round($number / 1000, 1) . 'K';
        }
        return $number;
    }
}
