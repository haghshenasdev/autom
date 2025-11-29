<?php

namespace App\Filament\Resources\TaskResource\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\TaskGroup;
use App\Models\Task;
use Morilog\Jalali\Jalalian;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class TasksTrendChart extends ChartWidget
{
    protected static ?string $heading = 'روند کارها در دسته‌بندی‌ها';

    // تعریف فیلترها
    protected function getFilters(): ?array
    {
        return [
            'weekly' => 'هفتگی',
            'monthly' => 'ماهانه',
            '3months' => 'سه ماهه',
            '6months' => 'شش ماهه',
            'yearly' => 'سالانه',

            // فیلتر نوع تاریخ
            'created_at' => 'بر اساس تاریخ ایجاد',
            'started_at' => 'بر اساس تاریخ شروع',
            'completed_at' => 'بر اساس تاریخ تکمیل',
            'ended_at' => 'بر اساس تاریخ پایان',
        ];
    }

    protected function getData(): array
    {
        $groups = TaskGroup::all();

        // انتخاب فیلتر بازه زمانی
        $timeFilter = $this->filter ?? 'monthly';
        // انتخاب فیلد تاریخ (پیش‌فرض created_at)
        $dateField = in_array($timeFilter, ['created_at','started_at','completed_at','ended_at'])
            ? $timeFilter
            : 'created_at';

        // تعیین بازه و نوع گروه‌بندی
        switch ($timeFilter) {
            case 'weekly':
                $startDate = now()->subWeeks(8);
                $step = '1 week';
                $unit = 'week';
                $format = 'W Y';
                break;

            case '3months':
                $startDate = now()->subMonths(3);
                $step = '1 month';
                $unit = 'month';
                $format = '%B %Y';
                break;

            case '6months':
                $startDate = now()->subMonths(6);
                $step = '1 month';
                $unit = 'month';
                $format = '%B %Y';
                break;

            case 'yearly':
                $startDate = now()->subYear();
                $step = '1 month';
                $unit = 'month';
                $format = '%B %Y';
                break;

            default: // ماهانه
                $startDate = now()->subMonths(6);
                $step = '1 month';
                $unit = 'month';
                $format = '%B %Y';
                break;
        }

        $endDate = now();
        $period = CarbonPeriod::create($startDate, $step, $endDate);

        // لیبل‌ها به صورت شمسی
        $labels = [];
        foreach ($period as $date) {
            $labels[] = Jalalian::fromCarbon($date)->format($format);
        }

        $datasets = [];

        foreach ($groups as $group) {
            $data = [];

            foreach ($period as $date) {
                $start = $date->copy()->startOf($unit);
                $end = $date->copy()->endOf($unit);

                $count = Task::whereHas('group', fn($q) => $q->where('task_groups.id', $group->id))
                    ->whereBetween($dateField, [$start, $end])
                    ->count();

                $data[] = $count;
            }

            if (array_sum($data) === 0) {
                continue;
            }

            $datasets[] = [
                'label' => $group->name,
                'data' => $data,
                'borderColor' => [
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
                'fill' => false,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    // رنگ پایدار بر اساس id گروه
    private function getmyColor(string $key): string
    {
        $hash = crc32($key);
        $r = ($hash & 0xFF0000) >> 16;
        $g = ($hash & 0x00FF00) >> 8;
        $b = ($hash & 0x0000FF);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
