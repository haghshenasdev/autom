<?php

namespace App\Filament\Widgets\UsersReport;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsersReportStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('تعداد کاربران',User::all()->count())->icon('heroicon-o-user'),
        ];
    }
}
