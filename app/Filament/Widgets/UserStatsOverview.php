<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CartableResource;
use App\Filament\Resources\LetterResource;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ReferralResource;
use App\Filament\Resources\TaskResource;
use App\Models\Cartable;
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

    public static function canView(): bool
    {
        return auth()->user()->can('widget_UserStatsOverview');
    }

    protected static ?int $sort = 1;
    protected function getStats(): array
    {
        $auth_id = auth()->id();
        return [
            Stat::make('نامه شما', $this->formatShortNumber(Letter::query()->where('user_id', $auth_id) // نامه‌هایی که user_id برابر با آیدی کاربر لاگین شده است
            ->orWhereHas('referrals', function ($query) use ($auth_id) {
                $query->where('to_user_id', $auth_id); // نامه‌هایی که Referral.to_user_id برابر با آیدی کاربر لاگین شده است
            })->count()))->icon('heroicon-o-envelope')->url(LetterResource::getUrl()),
            Stat::make('ارجاع بررسی نشده', $this->formatShortNumber(Referral::query()->where('to_user_id',$auth_id)->whereNot('checked',1)->count()))->url(ReferralResource::getUrl())->icon('heroicon-o-user'),
            Stat::make(' کار پوشه بررسی نشده', $this->formatShortNumber(Cartable::query()->where('user_id',$auth_id)->whereNot('checked',1)->count()))->url(CartableResource::getUrl())->icon('heroicon-o-user'),
            Stat::make('پروژه های شما', $this->formatShortNumber(Project::query()->where('user_id',$auth_id)->count()))->icon('heroicon-o-archive-box')->url(ProjectResource::getUrl()),
            Stat::make(' کار یا جلسه', $this->formatShortNumber(Task::query()->where('Responsible_id',$auth_id)->count()))->icon('heroicon-o-briefcase')->url(TaskResource::getUrl()),
            Stat::make('صورت جلسه ها', $this->formatShortNumber(Minutes::query()->where('typer_id',$auth_id)->count()))->icon('heroicon-o-document-text')->url(MinutesResource::getUrl()),
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
