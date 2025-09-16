<?php

namespace App\Filament\Resources\ProjectResource\Widgets;
use Filament\Widgets\ChartWidget;
use App\Models\Task;
use App\Models\User;

class TasksByUserChart extends ChartWidget
{
    public ?\App\Models\Project $record = null;

    protected static ?string $heading = 'وضعیت کارهای کاربران در پروژه';

    protected function getData(): array
    {
        // گرفتن همه تسک‌های مربوط به پروژه فعلی
        $tasks = $this->record->tasks()->with('responsible')->get();

        // گروه‌بندی تسک‌ها بر اساس مسئول
        $grouped = $tasks->groupBy(fn($task) => optional($task->responsible)->id);

        $labels = [];
        $assignedCounts = [];
        $completedCounts = [];

        foreach ($grouped as $userId => $userTasks) {
            $user = $userTasks->first()->responsible;

            if (!$user) continue;

            $labels[] = $user->name;
            $assignedCounts[] = $userTasks->count();
            $completedCounts[] = $userTasks->where('completed', true)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'محول‌شده',
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
