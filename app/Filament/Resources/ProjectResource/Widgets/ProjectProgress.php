<?php

namespace App\Filament\Resources\ProjectResource\Widgets;

use Filament\Widgets\Widget;

class ProjectProgress extends Widget
{
    protected static string $view = 'filament.resources.project-resource.widgets.project-progress';

    public $progress; // درصد پیشرفت
    public $record; // پروژه جاری

    public string|null $selectedYear = null;
    public array|null $betYear = null;

    public function mount($record): void
    {
        $this->record = $record;

        // محاسبه درصد پیشرفت
        $totalTasks = $record->required_amount != null ? $record->required_amount : ($this->betYear ? $this->record->tasks()->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->tasks()->count());
        $completedTasks = $this->betYear ?  $this->record->tasks()->where('completed','=',1)->whereBetween('tasks.created_at', $this->betYear)->count() : $this->record->tasks()->where('completed','=',1)->count();

        $this->progress = $totalTasks > 0 ? min(100,round(($completedTasks / $totalTasks) * 100)) : 0;
    }
}
