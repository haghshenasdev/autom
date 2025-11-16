<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Widgets\TaskProjectChart;
use App\Filament\Resources\ProjectResource\Widgets\YearSelector;
use App\Models\Project;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Morilog\Jalali\Jalalian;

class Record extends Page
{
    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.record';

    public $selectedYear = null;


    public $record; // رکورد انتخاب شده

//    public $data = ['selectedYear' => 1404];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedYear')
                    ->label('انتخاب سال')
                    ->options($this->getYears())
                    ->afterStateUpdated(function ($state, callable $set) {
                        redirect(request()->url() . '?year=' . $state);
                    })
            ])
            ->statePath('data')->live()->reactive();
    }

    protected function getYears(): array
    {
        $current = Jalalian::now()->getYear();
        $years = [];

        for ($i = 0; $i <= 5; $i++) {
            $years[$current - $i] = $current - $i;
        }

        return $years;
    }

    public function mount($id): void
    {
        $this->record = Project::findOrFail($id);
        $this->selectedYear = request()->query('year', Jalalian::now()->getYear());
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
