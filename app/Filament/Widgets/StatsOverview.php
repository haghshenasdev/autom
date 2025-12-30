<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ApproveResource;
use App\Filament\Resources\ContentResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\LetterResource;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\TaskResource;
use App\Models\Approve;
use App\Models\Content;
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
        $stats = [];
        if (auth()->user()->can('view_content')){
            $stats[] =  Stat::make('تعداد اسناد', $this->formatShortNumber(Content::query()->count()))->icon('heroicon-o-document')->url(ContentResource::getUrl());
        }
        return array_merge($stats,[
            Stat::make('تعداد نامه ها', $this->formatShortNumber(Letter::query()->count()))->icon('heroicon-o-envelope')->url(LetterResource::getUrl()),
            Stat::make('مراجعه کننده ها', $this->formatShortNumber(Customer::query()->count()))->icon('heroicon-o-user')->url(CustomerResource::getUrl()),
            Stat::make('تعداد دستورکار ها', $this->formatShortNumber(Project::query()->count()))->icon('heroicon-o-archive-box')->url(ProjectResource::getUrl()),
            Stat::make('فعالیت ها', $this->formatShortNumber(Task::query()->count()))->icon('heroicon-o-briefcase')->url(TaskResource::getUrl()),
            Stat::make('صورت جلسه ها', $this->formatShortNumber(Minutes::query()->count()))->icon('heroicon-o-document-text')->url(MinutesResource::getUrl()),
            Stat::make('مصوبه ها', $this->formatShortNumber(Approve::query()->count()))->icon('heroicon-o-document-check')->url(ApproveResource::getUrl()),
        ]);
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
