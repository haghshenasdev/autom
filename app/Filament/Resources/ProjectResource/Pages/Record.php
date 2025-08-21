<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Widgets\TaskProjectChart;
use App\Models\Project;
use Filament\Resources\Pages\Page;

class Record extends Page
{
    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.record';


    public $record; // رکورد انتخاب شده

    public function mount($id): void
    {
        $this->record = Project::findOrFail($id);
        self::$title = $this->record->name;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TaskProjectChart::make(['record' => $this->record]),
            ProjectResource\Widgets\ProjectProgress::make(['record' => $this->record]),
        ];
    }
}
