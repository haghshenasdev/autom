<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProjectResource\Widgets\ProjectGanttChart;
use App\Filament\Resources\ProjectResource\Widgets\TaskDelayChart;
use App\Filament\Resources\ProjectResource\Widgets\TasksByCityChart;
use App\Filament\Resources\TaskResource\Widgets\TaskCalenderCountChartForTasks;
use App\Filament\Resources\UserResource\Widgets\ActivityCalenderCountChart;
use App\Filament\Resources\UserResource\Widgets\StatsOverview;
use App\Filament\Resources\UserResource\Widgets\TasksGroupsPieChartUser;
use App\Filament\Resources\UserResource\Widgets\TasksTrendChartUser;
use App\Models\User;
use Exception;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Actions\Action;
use Filament\Pages\Page;
use Morilog\Jalali\Jalalian;

class MyReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static string $view = 'filament.resources.project-resource.pages.record';

    protected static ?string $navigationLabel = 'گزارش فعالیت من';
    protected static ?string $navigationGroup = 'سیستم';

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

    public function mount(): void
    {
        $this->record = auth()->user();
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
            StatsOverview::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            TaskCalenderCountChartForTasks::make(['user_id' => $this->record->id,'selectedYear' => $this->selectedYear,'betYear' => $this->betYear]),
            ActivityCalenderCountChart::make(['user_id' => $this->record->id,'selectedYear' => $this->selectedYear,'betYear' => $this->betYear]),
            TasksTrendChartUser::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            TasksGroupsPieChartUser::make(['record' => $this->record, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            TaskDelayChart::make(['user_id' => $this->record->id,'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            TasksByCityChart::make(['user_id' => $this->record->id, 'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
            ProjectGanttChart::make([ 'user_id' => $this->record->id,'selectedYear' => $this->selectedYear, 'betYear' => $this->betYear]),
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
