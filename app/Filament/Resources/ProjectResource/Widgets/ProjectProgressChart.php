<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\ChartWidget;

class ProjectProgressChart extends ChartWidget
{
    public ?\App\Models\Project $record = null;

    public string|null $selectedYear = null;
    public array|null $betYear = null;

    protected static ?string $heading = 'نمودار پیشرفت کلی دستورکار';

    protected function getType(): string
    {
        return 'pie'; // یا 'pie' یا 'bar'
    }

    protected function getData(): array
    {
        if ($this->selectedYear) self::$heading .= ' در سال '. $this->selectedYear;
        $total = $this->betYear ? $this->record->tasks()->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->tasks()->count();
        $completed = $this->betYear ?  $this->record->tasks()->where('completed','=',1)->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->tasks()->where('completed','=',1)->count();
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
