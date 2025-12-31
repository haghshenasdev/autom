<?php

namespace App\Filament\Resources\UserResource\Widgets\UsersReport;

use App\Models\User;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsersReportStatsOverview extends BaseWidget
{
    public string|null $selectedYear = null;
    public array|null $betYear = null; // [startCarbon, endCarbon]

    protected function getStats(): array
    {
        // تاریخ امروز
        $todayStart = Carbon::today();
        $todayEnd   = Carbon::now();

        // محاسبه فعال‌ترین کاربر امروز
        $activeToday = User::query()->where('id', '!=', 1)->withCount([
            'task_responsible as tasks_responsible_count' => function ($q) use ($todayStart, $todayEnd) {
                $q->where('completed', 1)
                    ->whereBetween('completed_at', [$todayStart, $todayEnd]);
            },
            //ایجاد کننده کار
//            'task_created as tasks_created_count' => function ($q) use ($todayStart, $todayEnd) {
//                $q->whereBetween('tasks.created_at', [$todayStart, $todayEnd]);
//            },
            'letters as letters_count' => function ($q) use ($todayStart, $todayEnd) {
                $q->whereBetween('letters.created_at', [$todayStart, $todayEnd]);
            },
            'minutes as minutes_count' => function ($q) use ($todayStart, $todayEnd) {
                $q->whereBetween('date', [$todayStart, $todayEnd]);
            },
        ])
            ->get()
            ->map(function ($user) {
                $user->total_activity = $user->tasks_responsible_count
                    + $user->tasks_created_count
                    + $user->letters_count
                    + $user->minutes_count;
                return $user;
            })
            ->sortByDesc('total_activity')
            ->first();

        // محاسبه فعال‌ترین کاربر در بازه انتخاب‌شده
        $activeInRange = null;
        if ($this->betYear) {
            [$start, $end] = $this->betYear;

            $activeInRange = User::query()->where('id', '!=', 1)->withCount([
                'task_responsible as tasks_responsible_count' => function ($q) use ($start, $end) {
                    $q->where('completed', 1)
                        ->whereBetween('completed_at', [$start, $end]);
                },
                'task_created as tasks_created_count' => function ($q) use ($start, $end) {
                    $q->whereBetween('tasks.created_at', [$start, $end]);
                },
                'letters as letters_count' => function ($q) use ($start, $end) {
                    $q->whereBetween('letters.created_at', [$start, $end]);
                },
                'minutes as minutes_count' => function ($q) use ($start, $end) {
                    $q->whereBetween('date', [$start, $end]);
                },
            ])
                ->get()
                ->map(function ($user) {
                    $user->total_activity = $user->tasks_responsible_count
                        + $user->tasks_created_count
                        + $user->letters_count
                        + $user->minutes_count;
                    return $user;
                })
                ->sortByDesc('total_activity')
                ->first();
        }else{
            // ✅ اگر تاریخ انتخاب نشده باشد، کل دیتا بدون فیلتر تاریخ
            $activeInRange = User::where('id', '!=', 1)
                ->withCount([
                    'task_responsible as tasks_responsible_count' => function ($q) {
                        $q->where('completed', 1);
                    },
                    'task_created as tasks_created_count',
                    'letters as letters_count',
                    'minutes as minutes_count',
                ])
                ->get()
                ->map(fn($user) => tap($user, function ($u) {
                    $u->total_activity = $u->tasks_responsible_count
                        + $u->tasks_created_count
                        + $u->letters_count
                        + $u->minutes_count;
                }))
                ->sortByDesc('total_activity')
                ->first();
        }

        return [
            Stat::make('تعداد کاربران', User::count())->icon('heroicon-o-user'),
            Stat::make('فعال‌ترین کاربر امروز تا هم‌اکنون', $activeToday?->name ?? '—')->icon('heroicon-o-user'),
            Stat::make('فعال‌ترین کاربر در بازه انتخاب‌شده', $activeInRange?->name ?? '—')->icon('heroicon-o-user'),
        ];
    }
}
