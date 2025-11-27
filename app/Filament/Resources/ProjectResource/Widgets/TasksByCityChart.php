<?php

namespace App\Filament\Resources\ProjectResource\Widgets;
use Filament\Widgets\ChartWidget;
use App\Models\Task;
use App\Models\User;

class TasksByCityChart extends ChartWidget
{
    public ?\App\Models\Project $record = null;

    protected static ?string $heading = 'وضعیت شهر ها در پروژه';

    protected function getData(): array
    {
        // گرفتن همه تسک‌های مربوط به پروژه فعلی
        $tasks = $this->record->tasks()->with('city')->get();

        // گروه‌بندی تسک‌ها بر اساس مسئول
        $grouped = $tasks->groupBy(fn($task) => optional($task->city)->id);

        $labels = [];
        $assignedCounts = [];
        $completedCounts = [];

        foreach ($grouped as $userId => $userTasks) {
            $user = $userTasks->first()->city;

            if (!$user) continue;

            $labels[] = $user->name;
            $assignedCounts[] = $userTasks->count();
            $completedCounts[] = $userTasks->where('completed', true)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'تعریف شده',
                    'data' => $assignedCounts,
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'انجام‌شده',
                    'data' => $completedCounts,
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    public function getColumnSpan(): int | string | array
    {
        return 'full'; // یا عددی مثل 12 برای پر کردن کل عرض
    }
}
