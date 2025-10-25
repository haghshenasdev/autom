<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Widgets\TaskProjectChart;
use App\Filament\Resources\ProjectResource\Widgets\YearSelector;
use App\Models\Project;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\Page;

class Record extends Page
{
    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.record';

    public $selectedYear = null;


    public $record; // رکورد انتخاب شده

    public function mount($id): void
    {
        $this->record = Project::findOrFail($id);
        self::$title = $this->record->name;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            YearSelector::make(),
            ProjectResource\Widgets\ProjectStateWidget::make(['record' => $this->record, 'selectedYear' => $this->selectedYear]),
            TaskProjectChart::make(['record' => $this->record, 'year' => $this->selectedYear]),
            ProjectResource\Widgets\ProjectProgress::make(['record' => $this->record, 'year' => $this->selectedYear]),
            ProjectResource\Widgets\ProjectProgressChart::make(['record' => $this->record, 'year' => $this->selectedYear]),
            ProjectResource\Widgets\TaskDelayChart::make(['record' => $this->record, 'year' => $this->selectedYear]),
            ProjectResource\Widgets\TasksByUserChart::make(['record' => $this->record, 'year' => $this->selectedYear]),
            ProjectResource\Widgets\ProjectGanttChart::make(['record' => $this->record, 'year' => $this->selectedYear]),
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
