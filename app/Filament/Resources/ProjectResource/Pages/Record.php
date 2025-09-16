<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Widgets\TaskProjectChart;
use App\Models\Project;
use Filament\Pages\Actions\Action;
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
            ProjectResource\Widgets\ProjectStateWidget::make(['record' => $this->record]),
            TaskProjectChart::make(['record' => $this->record]),
            ProjectResource\Widgets\ProjectProgress::make(['record' => $this->record]),
            ProjectResource\Widgets\ProjectProgressChart::make(['record' => $this->record]),
            ProjectResource\Widgets\TaskDelayChart::make(['record' => $this->record]),
            ProjectResource\Widgets\TasksByUserChart::make(['record' => $this->record]),
            ProjectResource\Widgets\ProjectGanttChart::make(['record' => $this->record]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('چاپ صفحه')
                ->icon('heroicon-o-printer')
                ->extraAttributes([
                    'onclick' => 'window.print()',
                ]),
        ];
    }
}
