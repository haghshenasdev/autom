<?php

namespace App\Filament\Widgets;

use App\Models\Approve;
use App\Models\Customer;
use App\Models\Letter;
use App\Models\Minutes;
use App\Models\Project;
use App\Models\Task;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Morilog\Jalali\CalendarUtils;

class StatsOverview extends BaseWidget
{
    use HasWidgetShield;
    protected static bool $isLazy = true;

    protected static ?int $sort = 1;
    protected function getStats(): array
    {
        return [
            Stat::make('تعداد نامه ها', $this->formatShortNumber(Letter::query()->count()))->icon('heroicon-o-envelope'),
            Stat::make('مراجعه کننده ها', $this->formatShortNumber(Customer::query()->count()))->icon('heroicon-o-user'),
            Stat::make('تعداد پروژه ها', $this->formatShortNumber(Project::query()->count()))->icon('heroicon-o-archive-box'),
            Stat::make('کار یا جلسه', $this->formatShortNumber(Task::query()->count()))->icon('heroicon-o-briefcase'),
            Stat::make('صورت جلسه ها', $this->formatShortNumber(Minutes::query()->count()))->icon('heroicon-o-document-text'),
            Stat::make('مصوبه ها', $this->formatShortNumber(Approve::query()->count()))->icon('heroicon-o-document-check'),
        ];
    }

    private function formatShortNumber($number): string
    {
        if ($number >= 1000000000) {
            return CalendarUtils::convertNumbers(round($number / 1000000000, 1)) . ' بیلیون';
        } elseif ($number >= 1000000) {
            return CalendarUtils::convertNumbers(round($number / 1000000, 1)) . ' میلیون';
        } elseif ($number >= 1000) {
            return CalendarUtils::convertNumbers(round($number / 1000, 1)) . ' هزار';
        }

        return $number;
    }
}
