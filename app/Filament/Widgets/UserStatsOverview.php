<?php

namespace App\Filament\Widgets;

use App\Models\Letter;
use App\Models\Minutes;
use App\Models\Project;
use App\Models\Referral;
use App\Models\Task;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Morilog\Jalali\CalendarUtils;

class UserStatsOverview extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;
    protected function getStats(): array
    {
        $auth_id = auth()->id();
        return [
            Stat::make('تعداد نامه ها', $this->formatShortNumber(Letter::query()->where('user_id',$auth_id)->count()))->icon('heroicon-o-envelope'),
            Stat::make('ارجاع به شما', $this->formatShortNumber(Referral::query()->where('to_user_id',$auth_id)->count()))->icon('heroicon-o-user'),
            Stat::make('پروژه های شما', $this->formatShortNumber(Project::query()->where('user_id',$auth_id)->count()))->icon('heroicon-o-archive-box'),
            Stat::make(' کار یا جلسه', $this->formatShortNumber(Task::query()->where('Responsible_id',$auth_id)->count()))->icon('heroicon-o-briefcase'),
            Stat::make('صورت جلسه ها', $this->formatShortNumber(Minutes::query()->where('typer_id',$auth_id)->count()))->icon('heroicon-o-document-text'),
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

        return CalendarUtils::convertNumbers($number);
    }
}
