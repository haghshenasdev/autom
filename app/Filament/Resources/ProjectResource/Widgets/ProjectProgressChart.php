<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\ChartWidget;

class ProjectProgressChart extends ChartWidget
{
    public ?\App\Models\Project $record = null;

    protected static ?string $heading = 'نمودار پیشرفت کلی پروژه';

    protected function getType(): string
    {
        return 'pie'; // یا 'pie' یا 'bar'
    }

    protected function getData(): array
    {
        $total = $this->record->tasks()->count();
        $completed = $this->record->tasks()->where('completed', true)->count();
        $incomplete = $total - $completed;

        return [
            'datasets' => [
                [
                    'label' => 'پیشرفت',
                    'data' => [$completed, $incomplete],
                    'backgroundColor' => ['#10b981', '#f59e0b'], // سبز و نارنجی
                ],
            ],
            'labels' => ['انجام‌شده', 'در انتظار'],
        ];
    }
}
