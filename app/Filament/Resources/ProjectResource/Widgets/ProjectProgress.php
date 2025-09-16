<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\Widget;

class ProjectProgress extends Widget
{
    protected static string $view = 'filament.resources.project-resource.widgets.project-progress';

    public $progress; // درصد پیشرفت
    public $record; // پروژه جاری

    public function mount($record): void
    {
        $this->record = $record;

        // محاسبه درصد پیشرفت
        $totalTasks = $record->required_amount != null ? $record->required_amount : $this->record->tasks()->count();
        $completedTasks = $this->record->tasks()->where('completed', true)->count();

        $this->progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
    }
}
