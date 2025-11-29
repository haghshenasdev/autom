<?php

namespace App\Filament\Resources\UserResource\Widgets\UsersReport;

use Filament\Widgets\ChartWidget;
use App\Models\User;
use App\Models\TaskGroup;

class UsersTasksGroupsChart extends ChartWidget
{
    protected static ?string $heading = 'مقایسه کاربران در گروه‌های کاری';

    protected function getData(): array
    {
        // همه گروه‌ها
        $groups = TaskGroup::with('tasks')->get();

        // همه کاربران به همراه کارها و گروه‌ها
        $users = User::with(['task_responsible.group'])->get();

        // حذف کاربر شماره 1
        $users = $users->where('id', '!=', 1);

        // حذف کاربرانی که هیچ کار در هیچ گروهی ندارند
        $users = $users->filter(function ($user) {
            return $user->task_responsible->isNotEmpty();
        });

        // حذف گروه‌هایی که هیچ کار ندارند
        $groups = $groups->filter(function ($group) {
            return $group->tasks->isNotEmpty();
        });

        $datasets = [];

        foreach ($groups as $index => $group) {
            $datasets[] = [
                'label' => $group->name,
                'data' => $users->map(function ($user) use ($group) {
                    return $user->task_responsible
                        ->filter(fn($task) => $task->group->contains($group))
                        ->count();
                })->toArray(),
                'backgroundColor' => $this->getmyColor($index),
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $users->pluck('name')->toArray(),
        ];
    }

    // رنگ ثابت بر اساس ایندکس گروه
    private function getmyColor(int $index): string
    {
        $colors = [
            '#3b82f6', // آبی
            '#10b981', // سبز
            '#f59e0b', // نارنجی
            '#ef4444', // قرمز
            '#6366f1', // بنفش
            '#14b8a6', // فیروزه‌ای
            '#84cc16', // سبز روشن
            '#d946ef', // صورتی
        ];

        return $colors[$index % count($colors)];
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
