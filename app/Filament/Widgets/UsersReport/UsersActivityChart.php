<?php

namespace App\Filament\Widgets\UsersReport;

use Filament\Widgets\ChartWidget;
use App\Models\User;

class UsersActivityChart extends ChartWidget
{
    protected static ?string $heading = 'فعالیت کاربران';

    protected function getData(): array
    {
        $users = User::withCount([
            'task_responsible',
            'minutes',
            'referral',
            'letters',
        ])->get();

        // فیلتر کردن کاربرانی که همه شمارش‌ها صفر است
        $users = $users->filter(function ($user) {
            return $user->task_responsible_count > 0
                || $user->minutes_count > 0
                || $user->referral_count > 0
                || $user->letters_count > 0;
        });

        return [
            'datasets' => [
                [
                    'label' => 'کارها',
                    'data' => $users->pluck('task_responsible_count')->toArray(),
                    'backgroundColor' => '#3b82f6',
                ],
                [
                    'label' => 'صورتجلسه‌ها',
                    'data' => $users->pluck('minutes_count')->toArray(),
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => 'ارجاعات',
                    'data' => $users->pluck('referral_count')->toArray(),
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'نامه‌ها',
                    'data' => $users->pluck('letters_count')->toArray(),
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $users->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
    public function getColumnSpan(): int | string | array
    {
        return 'full'; // پر کردن عرض صفحه
    }
}
