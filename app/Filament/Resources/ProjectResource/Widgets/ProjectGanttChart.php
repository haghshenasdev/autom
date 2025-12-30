<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\ChartWidget;

class ProjectGanttChart extends ChartWidget
{
    public ?\App\Models\Project $record = null;

    protected static ?string $heading = 'گانت چارت دستورکار';

    protected function getType(): string
    {
        return 'bar'; // استفاده از نمودار میله‌ای افقی
    }

    protected function getData(): array
    {
        $tasks = $this->record
            ->tasks()
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->get();

        $labels = [];
        $durations = [];

        foreach ($tasks as $task) {
            $labels[] = $task->title;

            // محاسبه مدت زمان به روز
            $start = \Carbon\Carbon::parse($task->started_at);
            $end = \Carbon\Carbon::parse($task->ended_at);
            $duration = $end->diffInDays($start);

            $durations[] = $duration;
        }

        return [
            'datasets' => [
                [
                    'label' => 'مدت زمان (روز)',
                    'data' => $durations,
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $labels,
        ];
    }

    public function getColumnSpan(): int | string | array
    {
        return 'full'; // پر کردن عرض صفحه
    }
}
