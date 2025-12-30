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

    protected static ?string $heading = 'وضعیت شهر ها در دستورکار';

    protected function getFilters(): ?array
    {
        return [
                'all' => 'همه شهرها',
                'counties' => 'نمایش شهرستانی',
            ] + City::whereNull('parent_id')
                    ->pluck('name', 'id')
                    ->all();
    }

    protected function getData(): array
    {
        $tasks = $this->record->tasks()->with('city')->get();
        $filter = $this->filter ?? 'all';

        $labels = [];
        $assignedCounts = [];
        $completedCounts = [];

        if ($filter === 'all') {
            // حالت نرمال: همه‌ی شهرها جداگانه
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

            $stats = $stats->sortByDesc('completed')->values();
            $labels = $stats->pluck('label')->all();
            $assignedCounts = $stats->pluck('assigned')->all();
            $completedCounts = $stats->pluck('completed')->all();

        } elseif ($filter === 'counties') {
            // حالت شهرستانی: هر شهرستان یک ستون
            $counties = City::whereNull('parent_id')->get();

            foreach ($counties as $county) {
                $cityIds = City::where('parent_id', $county->id)->pluck('id');
                $countyTasks = $tasks->filter(fn($task) => $task->city && $cityIds->contains($task->city->id));

                $labels[] = $county->name;
                $assignedCounts[] = $countyTasks->count();
                $completedCounts[] = $countyTasks->where('completed', true)->count();
            }

        } else {
            // حالت انتخاب یک شهرستان خاص: نمایش شهرهای زیرمجموعه
            $cityIds = City::where('parent_id', $filter)->pluck('id');
            $filteredTasks = $tasks->filter(fn($task) => $task->city && $cityIds->contains($task->city->id));

            $grouped = $filteredTasks->groupBy(fn($task) => optional($task->city)->id);

            foreach ($grouped as $cityId => $cityTasks) {
                $city = $cityTasks->first()->city;
                if (!$city) continue;

                $labels[] = $city->name;
                $assignedCounts[] = $cityTasks->count();
                $completedCounts[] = $cityTasks->where('completed', true)->count();
            }
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
