<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Project;
use Filament\Widgets\ChartWidget;
use Morilog\Jalali\Jalalian;

class TaskProjectChart extends ChartWidget
{
    protected static ?string $heading = 'تقویم کار ها';

    public ?Project $record = null;

    protected function getData(): array
    {
        // محاسبه تاریخ‌های شروع و پایان سال شمسی
        $startOfYear = Jalalian::now()->subMonths(Jalalian::now()->getMonth() - 1)->subDays(Jalalian::now()->getDay() - 1)->toCarbon();
        $endOfYear = Jalalian::fromFormat('Y-m-d', Jalalian::now()->getYear() . '-12-30')->toCarbon();

        $tasks = $this->record->tasks()->whereBetween('completed_at', [$startOfYear, $endOfYear])
            ->get(['completed_at']);

// گروه‌بندی داده‌ها بر اساس ماه‌های شمسی
        $groupedData = collect([]);

        for ($i = 1; $i <= 12; $i++) {
            $monthKey = Jalalian::now()->getYear() . '-' . str_pad($i, 2, '0', STR_PAD_LEFT); // سال-ماه (شمسی)
            $groupedData[$monthKey] = 0; // مقدار پیش‌فرض برای هر ماه
        }

        $tasks->each(function ($task) use (&$groupedData) {
            // بررسی وجود فیلد completed_at و عدم null بودن آن
            if (optional($task)->completed_at) {
                $jalaliDate = Jalalian::fromDateTime($task->completed_at); // تبدیل تاریخ میلادی به شمسی
                $monthKey = $jalaliDate->format('Y-m'); // کلید گروه‌بندی (سال-ماه)
                $groupedData[$monthKey] += 1; // شمارش تعداد تسک‌ها برای هر ماه
            }
        });


        $values = $groupedData->values();

        return [
            'datasets' => [
                [
                    'label' => "کار های انجام شده (سال " . Jalalian::now()->getYear() . ")",
                    'data' => $values,
                ],
            ],
            'labels' => ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
