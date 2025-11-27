<?php

namespace App\Filament\Resources\ProjectResource\Widgets;
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
        return [
            'county' => Select::make('county')
                ->label('شهرستان')
                ->options(
                    \App\Models\City::whereNull('parent_id')
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->placeholder('همه شهرستان‌ها'),
        ];
    }

    protected function getData(): array
    {
        $tasks = $this->record->tasks()->with('city')->get();

        // اگر فیلتر شهرستان انتخاب شده باشد
        $countyId = $this->filterFormData['county'] ?? null;
        if ($countyId) {
            $cityIds = \App\Models\City::where('parent_id', $countyId)->pluck('id');
            $tasks = $tasks->filter(fn($task) => $task->city && $cityIds->contains($task->city->id));
        }

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

        return [
            'datasets' => [
                [
                    'label' => 'تعریف شده',
                    'data' => $stats->pluck('assigned')->all(),
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'انجام‌شده',
                    'data' => $stats->pluck('completed')->all(),
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => $stats->pluck('label')->all(),
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
