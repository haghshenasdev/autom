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
        $groups = TaskGroup::all();

        // همه کاربران
        $users = User::query()
            ->where('id', '!=', 1) // حذف کاربر شماره 1
            ->get();

        $datasets = [];

        foreach ($groups as $index => $group) {
            // شمارش تعداد کارهای هر کاربر در این گروه با کوئری مستقیم
            $data = $users->map(function ($user) use ($group) {
                return $user->task_responsible()
                    ->whereHas('group', fn($q) => $q->where('task_groups.id', $group->id))
                    ->count();
            });

            // اگر همه مقادیر صفر بود، این گروه را حذف کن
            if ($data->sum() === 0) {
                continue;
            }

            $datasets[] = [
                'label' => $group->name,
                'data' => $data->toArray(),
                'backgroundColor' => $this->getmyColor($index),
            ];
        }

        // حذف کاربرانی که هیچ داده‌ای ندارند
        $labels = [];
        $finalData = [];
        foreach ($users as $userIndex => $user) {
            $total = 0;
            foreach ($datasets as $dataset) {
                $total += $dataset['data'][$userIndex];
            }
            if ($total > 0) {
                $labels[] = $user->name;
                foreach ($datasets as $i => $dataset) {
                    $finalData[$i][] = $dataset['data'][$userIndex];
                }
            }
        }

        // بازسازی datasets با داده‌های فیلتر شده
        foreach ($datasets as $i => $dataset) {
            $datasets[$i]['data'] = $finalData[$i] ?? [];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
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
