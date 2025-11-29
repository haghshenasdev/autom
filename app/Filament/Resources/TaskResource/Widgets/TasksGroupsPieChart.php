<?php

namespace App\Filament\Resources\TaskResource\Widgets;

use App\Models\TaskGroup;
use Filament\Widgets\ChartWidget;

class TasksGroupsPieChart extends ChartWidget
{
    protected static ?string $heading = 'توزیع کارها بر اساس دسته‌بندی';

    protected function getData(): array
    {
        // همه گروه‌ها همراه با شمارش کارها
        $groups = TaskGroup::withCount('tasks')->get();

        // حذف گروه‌هایی که هیچ کار ندارند
        $groups = $groups->filter(fn($group) => $group->tasks_count > 0);

        return [
            'datasets' => [
                [
                    'data' => $groups->pluck('tasks_count')->toArray(),
                    'backgroundColor' => [
                        '#3b82f6', // آبی
                        '#10b981', // سبز
                        '#f59e0b', // نارنجی
                        '#ef4444', // قرمز
                        '#6366f1', // بنفش
                        '#14b8a6', // فیروزه‌ای
                        '#84cc16', // سبز روشن
                        '#d946ef', // صورتی
                        '#f97316', // نارنجی تیره
                        '#22c55e', // سبز چمنی
                        '#0ea5e9', // آبی فیروزه‌ای
                        '#a855f7', // بنفش روشن
                        '#eab308', // زرد
                        '#dc2626', // قرمز تیره
                        '#06b6d4', // آبی آسمانی
                        '#65a30d', // سبز زیتونی
                        '#9333ea', // بنفش پررنگ
                        '#f43f5e', // قرمز صورتی
                        '#0891b2', // آبی نفتی
                        '#c2410c', // قهوه‌ای نارنجی
                    ],
                ],
            ],
            'labels' => $groups->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
