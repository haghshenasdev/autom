<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class TaskDelayChart extends ChartWidget
{
    public ?\App\Models\Project $record = null;

    protected static ?string $heading = 'نمودار تأخیر تسک‌ها';

    protected function getType(): string
    {
        return 'pie'; // یا 'bar' برای نمودار ستونی
    }

    protected function getData(): array
    {
        $tasks = $this->record->tasks()
            ->whereNotNull('started_at')
            ->whereNotNull('ended_at')
            ->get();

        $onTime = 0;
        $delayed = 0;

        foreach ($tasks as $task) {
            $start = Carbon::parse($task->started_at);
            $end = Carbon::parse($task->ended_at);
            $duration = $end->diffInDays($start);

            if ($duration <= 5) {
                $onTime++;
            } else {
                $delayed++;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'وضعیت تسک‌ها',
                    'data' => [$onTime, $delayed],
                    'backgroundColor' => ['#10b981', '#ef4444'], // سبز و قرمز
                ],
            ],
            'labels' => ['به‌موقع (≤ ۵ روز)', 'با تأخیر (> ۵ روز)'],
        ];
    }
}
