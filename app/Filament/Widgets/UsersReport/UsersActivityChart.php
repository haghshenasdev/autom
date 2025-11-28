<?php

namespace App\Filament\Widgets\UsersReport;

use Filament\Widgets\ChartWidget;
use App\Models\User;

class UsersActivityChart extends ChartWidget
{
    protected static ?string $heading = 'فعالیت کاربران';

    protected function getFilters(): ?array
    {
        return [
            'all' => 'همه',
            'tasks' => 'کارها',
            'minutes' => 'صورتجلسه‌ها',
            'referrals' => 'ارجاعات',
            'letters' => 'نامه‌ها',
        ];
    }

    protected function getData(): array
    {
        $users = User::withCount([
            'task_responsible',
            'minutes',
            'referral',
            'letters',
        ])->where('id', '!=', 1)->get();

        // فیلتر کردن کاربرانی که همه شمارش‌ها صفر است
        $users = $users->filter(function ($user) {
            return $user->task_responsible_count > 0
                || $user->minutes_count > 0
                || $user->referral_count > 0
                || $user->letters_count > 0;
        });

        $datasets = [];

        // بررسی فیلتر انتخابی
        switch ($this->filter) {
            case 'tasks':
                $datasets[] = [
                    'label' => 'کارها',
                    'data' => $users->pluck('task_responsible_count')->toArray(),
                    'backgroundColor' => '#3b82f6',
                ];
                break;

            case 'minutes':
                $datasets[] = [
                    'label' => 'صورتجلسه‌ها',
                    'data' => $users->pluck('minutes_count')->toArray(),
                    'backgroundColor' => '#10b981',
                ];
                break;

            case 'referrals':
                $datasets[] = [
                    'label' => 'ارجاعات',
                    'data' => $users->pluck('referral_count')->toArray(),
                    'backgroundColor' => '#f59e0b',
                ];
                break;

            case 'letters':
                $datasets[] = [
                    'label' => 'نامه‌ها',
                    'data' => $users->pluck('letters_count')->toArray(),
                    'backgroundColor' => '#ef4444',
                ];
                break;

            default: // حالت "همه"
                $datasets = [
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
                ];
                break;
        }

        return [
            'datasets' => $datasets,
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
