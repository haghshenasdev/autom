<?php

namespace App\Filament\Resources\UserResource\Widgets\UsersReport;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class UsersActivityChart extends ChartWidget
{
    protected static ?string $heading = 'فعالیت کاربران';

    public string|null $selectedYear = null;
    public array|null $betYear = null; // [startCarbon, endCarbon]

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
        [$start, $end] = $this->betYear ?? [null, null];

        $users = User::withCount([
            'task_responsible' => function ($query) use ($start, $end) {
                if ($start && $end) {
                    $query->whereBetween('tasks.created_at', [$start, $end]);
                }
            },
            'minutes' => function ($query) use ($start, $end) {
                if ($start && $end) {
                    $query->whereBetween('minutes.date', [$start, $end]);
                }
            },
            'referral' => function ($query) use ($start, $end) {
                if ($start && $end) {
                    $query->whereBetween('referrals.created_at', [$start, $end]);
                }
            },
            'letters' => function ($query) use ($start, $end) {
                if ($start && $end) {
                    $query->whereBetween('letters.created_at', [$start, $end]);
                }
            },

            // شمارش شرطی برای کارهای انجام شده و انجام نشده
            'task_responsible as tasks_completed_count' => function ($query) use ($start, $end) {
                $query->where('completed', true);
                if ($start && $end) {
                    $query->whereBetween('completed_at', [$start, $end]);
                }
            },
            'task_responsible as tasks_incompleted_count' => function ($query) use ($start, $end) {
                $query->where(function ($q) {
                    $q->whereNull('completed')
                        ->orWhere('completed', 0);
                });
                if ($start && $end) {
                    $query->whereBetween('created_at', [$start, $end]);
                }
            },
        ])->where('id', '!=', 1)->get();

        // فیلتر کردن کاربرانی که همه شمارش‌ها صفر است
        $users = $users->filter(function ($user) {
            return $user->task_responsible_count > 0
                || $user->minutes_count > 0
                || $user->referral_count > 0
                || $user->letters_count > 0;
        });

        $datasets = [];

        switch ($this->filter) {
            case 'tasks':
                $datasets[] = [
                    'label' => 'کارهای انجام شده',
                    'data' => $users->pluck('tasks_completed_count')->toArray(),
                    'backgroundColor' => '#3b82f6',
                ];
                $datasets[] = [
                    'label' => 'کارهای انجام نشده',
                    'data' => $users->pluck('tasks_incompleted_count')->toArray(),
                    'backgroundColor' => '#ef4444',
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

    protected function getOptions(): array { return [ 'indexAxis' => 'y' ]; }
}
