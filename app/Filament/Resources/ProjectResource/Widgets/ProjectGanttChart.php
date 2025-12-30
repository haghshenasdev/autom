<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use App\Models\Task;
use Filament\Widgets\ChartWidget;

class ProjectGanttChart extends ChartWidget
{
    public ?\App\Models\Project $record = null;
    public ?int $user_id = null;

    public string|null $selectedYear = null;
    public array|null $betYear = null; // [startCarbon, endCarbon]

    protected static ?string $heading = 'گانت چارت ';

    protected function getType(): string
    {
        return 'bar'; // استفاده از نمودار میله‌ای افقی
    }

    protected function getData(): array
    {
        $query = null;
        if ($this->record) {
            $query = $this->record->tasks();
        }else{
            $query = Task::query();
            if ($this->user_id) $query->where('Responsible_id', $this->user_id);
        }

        if ($this->selectedYear && $this->betYear) {
            $query->whereBetween('created_at', $this->betYear);
        }

        $tasks = $query
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
