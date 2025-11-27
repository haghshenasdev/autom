<?php

namespace App\Filament\Resources\ProjectResource\Widgets;
use App\Models\City;
use Filament\Forms\Components\Select;
use Filament\Widgets\ChartWidget;
use App\Models\Task;
use App\Models\User;

class TasksByCityChart extends ChartWidget
{
    public ?\App\Models\Project $record = null;

    protected static ?string $heading = 'وضعیت شهر ها در پروژه';

    protected function getFilters(): ?array
    {
        return [null => 'همه شهرستان‌ها'] + City::whereNull('parent_id')
                    ->pluck('name', 'id')
                    ->all();
    }

    protected function getData(): array
    {
        $tasks = $this->record->tasks()->with('city')->get();

        $countyId = $this->filterFormData['county'] ?? null;

        $labels = [];
        $assignedCounts = [];
        $completedCounts = [];

        if ($countyId) {
            // همه‌ی شهرهای زیرمجموعه‌ی شهرستان انتخاب‌شده
            $cityIds = City::where('parent_id', $countyId)->pluck('id');

            $filteredTasks = $tasks->filter(fn($task) => $task->city && $cityIds->contains($task->city->id));

            $labels[] = City::find($countyId)?->name ?? 'شهرستان انتخابی';
            $assignedCounts[] = $filteredTasks->count();
            $completedCounts[] = $filteredTasks->where('completed', true)->count();
        } else {
            // حالت عادی: گروه‌بندی بر اساس شهر
            $grouped = $tasks->groupBy(fn($task) => optional($task->city)->id);

            $stats = collect();
            foreach ($grouped as $cityId => $cityTasks) {
                $city = $cityTasks->first()->city;
                if (!$city) continue;

                $stats->push([
                    'label' => $city->name,
                    'assigned' => $cityTasks->count(),
                    'completed' => $cityTasks->where('completed', true)->count(),
                ]);
            }

            // مرتب‌سازی بر اساس بیشترین انجام‌شده
            $stats = $stats->sortByDesc('completed')->values();

            $labels = $stats->pluck('label')->all();
            $assignedCounts = $stats->pluck('assigned')->all();
            $completedCounts = $stats->pluck('completed')->all();
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

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }
}
