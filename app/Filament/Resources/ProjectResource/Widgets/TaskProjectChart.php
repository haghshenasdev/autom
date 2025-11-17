<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Project;
use Filament\Widgets\ChartWidget;
use Morilog\Jalali\Jalalian;

class TaskProjectChart extends ChartWidget
{
    protected static ?string $heading = 'تقویم کار ها';

    public string|null $selectedYear = null;
    public array|null $betYear = null; // [startCarbon, endCarbon]

    public ?Project $record = null;

    protected function getData(): array
    {
        if ($this->selectedYear && $this->betYear) {
            // فقط همان سال
            $tasks = $this->record->tasks()
                ->whereBetween('completed_at', $this->betYear)
                ->get(['completed_at']);
        } else {
            // همه سال‌ها
            $tasks = $this->record->tasks()->get(['completed_at']);
        }

        $groupedData = collect([]);

        if ($this->selectedYear) {
            // کلیدها: سال-ماه
            for ($i = 1; $i <= 12; $i++) {
                $monthKey = $this->selectedYear . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                $groupedData[$monthKey] = 0;
            }
        } else {
            // کلیدها: فقط ماه (تجمیع همه سال‌ها)
            for ($i = 1; $i <= 12; $i++) {
                $monthKey = str_pad($i, 2, '0', STR_PAD_LEFT);
                $groupedData[$monthKey] = 0;
            }
        }

        $tasks->each(function ($task) use (&$groupedData) {
            if (optional($task)->completed_at) {
                $jalaliDate = Jalalian::fromDateTime($task->completed_at);

                if ($this->selectedYear) {
                    $monthKey = $jalaliDate->format('Y-m'); // مثل 1403-01
                } else {
                    $monthKey = $jalaliDate->format('m');   // فقط ماه، مثل 01
                }

                if (isset($groupedData[$monthKey])) {
                    $groupedData[$monthKey] += 1;
                }
            }
        });

        $values = $groupedData->values();

        return [
            'datasets' => [
                [
                    'label' => $this->selectedYear
                        ? "کارهای انجام شده (سال {$this->selectedYear})"
                        : "کارهای انجام شده (همه سال‌ها)",
                    'data' => $values,
                ],
            ],
            'labels' => [
                'فروردین', 'اردیبهشت', 'خرداد', 'تیر',
                'مرداد', 'شهریور', 'مهر', 'آبان',
                'آذر', 'دی', 'بهمن', 'اسفند'
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
