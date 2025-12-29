<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\Task;
use App\Models\TaskGroup;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Morilog\Jalali\Jalalian;

class TasksTrendChartUser extends ChartWidget
{
    protected static ?string $heading = 'روند فعالیت ها در دسته‌بندی‌ها';

    public ?array $betYear = null;
    public string|null $selectedYear = null;

    public $record;

    // تعریف فیلترها
    protected function getFilters(): ?array
    {
        return [
            '6months-completed_at' => 'شش ماهه بر اساس تاریخ انجام',
            '6months-created_at'   => 'شش ماهه بر اساس تاریخ ایجاد',
            '6months-started_at'   => 'شش ماهه بر اساس تاریخ شروع',
            '6months-ended_at'     => 'شش ماهه بر اساس تاریخ پایان',

            'weekly-completed_at'  => 'هفتگی بر اساس تاریخ انجام',
            'weekly-created_at'    => 'هفتگی بر اساس تاریخ ایجاد',
            'weekly-started_at'    => 'هفتگی بر اساس تاریخ شروع',
            'weekly-ended_at'      => 'هفتگی بر اساس تاریخ پایان',

            'monthly-completed_at' => 'ماهانه بر اساس تاریخ انجام',
            'monthly-created_at'   => 'ماهانه بر اساس تاریخ ایجاد',
            'monthly-started_at'   => 'ماهانه بر اساس تاریخ شروع',
            'monthly-ended_at'     => 'ماهانه بر اساس تاریخ پایان',

            '3months-completed_at' => 'سه ماهه بر اساس تاریخ انجام',
            '3months-created_at'   => 'سه ماهه بر اساس تاریخ ایجاد',
            '3months-started_at'   => 'سه ماهه بر اساس تاریخ شروع',
            '3months-ended_at'     => 'سه ماهه بر اساس تاریخ پایان',

            'yearly-completed_at'  => 'سالانه بر اساس تاریخ انجام',
            'yearly-created_at'    => 'سالانه بر اساس تاریخ ایجاد',
            'yearly-started_at'    => 'سالانه بر اساس تاریخ شروع',
            'yearly-ended_at'      => 'سالانه بر اساس تاریخ پایان',
        ];
    }


    protected function getData(): array
    {
        $groups = TaskGroup::all();

        // فیلتر انتخابی
        $filter = $this->filter ?? '6months-completed_at';
        [$timeFilter, $dateField] = explode('-', $filter);

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

                $query = Task::whereHas('group', fn($q) => $q->where('task_groups.id', $group->id))
                    ->whereBetween($dateField, [$start, $end])->where('responsible_id', $this->record->id);

                // ✅ اگر سال انتخاب شده وجود داشت، محدودیت سال رو هم اعمال کن
                if ($this->betYear) {
                    $query->whereBetween($dateField, $this->betYear);
                }

                $count = $query->count();
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

    public function getColumnSpan(): int|string|array
    { return 'full'; }
}
