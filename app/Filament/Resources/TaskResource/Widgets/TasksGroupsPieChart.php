<?php

namespace App\Filament\Resources\TaskResource\Widgets;

use App\Models\TaskGroup;
use Filament\Widgets\ChartWidget;

class TasksGroupsPieChart extends ChartWidget
{
    protected static ?string $heading = 'توزیع کارها بر اساس دسته‌بندی';

    protected function getData(): array
    {
        // همه گروه‌ها همراه با شمارش کارها
        $groups = TaskGroup::withCount('tasks')->get();

        // حذف گروه‌هایی که هیچ کار ندارند
        $groups = $groups->filter(fn($group) => $group->tasks_count > 0);

        return [
            'datasets' => [
                [
                    'data' => $groups->pluck('tasks_count')->toArray(),
                    'backgroundColor' => $groups->map(fn($group) => $this->getmyColor((string)$group->id))->toArray(),
                ],
            ],
            'labels' => $groups->pluck('name')->toArray(),
        ];
    }

    // تولید رنگ پایدار بر اساس id گروه
    private function getmyColor(string $key): string
    {
        $hash = crc32($key);
        $r = ($hash & 0xFF0000) >> 16;
        $g = ($hash & 0x00FF00) >> 8;
        $b = ($hash & 0x0000FF);

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
