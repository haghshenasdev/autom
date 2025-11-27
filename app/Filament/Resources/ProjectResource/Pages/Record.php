<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Widgets\TaskProjectChart;
use App\Filament\Resources\ProjectResource\Widgets\YearSelector;
use App\Models\Project;
use Exception;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;
use Morilog\Jalali\Jalalian;
use Safe\Exceptions\ExecException;

class Record extends Page
{
    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.record';

    public string|null $selectedYear = null;
    public array|null $betYear = null;


    public $record; // رکورد انتخاب شده

//    public $data = ['selectedYear' => 1404];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedYear')
                    ->label('انتخاب سال')->placeholder('همه سال ها')
                    ->options($this->getYears())
                    ->afterStateUpdated(function ($state) {
                        $this->selectedYear = $state;
                        $this->js("window.location.href = window.location.pathname + '?year={$state}'");
                    })->live()
            ])
            ->live();
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

    public function getGregorianRangeForJalaliYear($jalaliYear): array|null
    {
        try {
            // شروع سال شمسی (اول فروردین)
            $start = Jalalian::fromFormat('Y-m-d',"{$jalaliYear}-01-01")->toCarbon()->startOfDay();

            // پایان سال شمسی (آخر اسفند)
            $end = Jalalian::fromFormat('Y-m-d',"{$jalaliYear}-12-29")->toCarbon()->endOfDay();

            // اگر سال کبیسه باشه، اسفند 30 روزه میشه
            if (Jalalian::fromFormat('Y-m-d',"{$jalaliYear}-12-30")->getMonth() === 12) {
                $end = Jalalian::fromFormat('Y-m-d',"{$jalaliYear}-12-30")->toCarbon()->endOfDay();
            }

            return [$start, $end];
        }catch (Exception $e){
            return null;
        }
    }

    public function mount($id): void
    {
        $this->record = Project::findOrFail($id);
        self::$title = $this->record->name;
        $requestData = request()->validate([
            'year' => ['nullable', 'numeric'],
        ]);
        if (isset($requestData['year'])){
            $this->selectedYear = $requestData['year'];
            if ($requestData['year']) $this->betYear = $this->getGregorianRangeForJalaliYear($requestData['year']);
        }
    }

    protected function mygetHeaderWidgets(): array
    {
        return [
            ProjectResource\Widgets\ProjectStateWidget::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            TaskProjectChart::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            ProjectResource\Widgets\ProjectProgress::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            ProjectResource\Widgets\ProjectProgressChart::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            ProjectResource\Widgets\TaskDelayChart::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            ProjectResource\Widgets\TasksByUserChart::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            ProjectResource\Widgets\TasksByCityChart::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            ProjectResource\Widgets\ProjectGanttChart::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
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
