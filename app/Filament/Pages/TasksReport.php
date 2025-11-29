<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProjectResource\Widgets\YearSelector;
use App\Filament\Resources\TaskResource\Widgets\TasksGroupsPieChart;
use App\Filament\Resources\TaskResource\Widgets\TasksTrendChart;
use Exception;
use Filament\Pages\Page;
use Morilog\Jalali\Jalalian;

class TasksReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tasks-report';

    protected static ?string $title = 'گزارش جامع کار ها';
    public ?array $betYear;
    public string|null $selectedYear = null;

    protected function getHeaderWidgets(): array
    {
        return [
            YearSelector::make(['selectedYear' => $this->selectedYear]),
            TasksGroupsPieChart::make(['selectedYear' => $this->selectedYear,'betYear' => $this->betYear]),
            TasksTrendChart::make(),
        ];
    }

    public function mount(): void
    {
        $requestData = request()->validate([
            'year' => ['nullable', 'numeric'],
        ]);
        if (isset($requestData['year'])){
            $this->selectedYear = $requestData['year'];
            if ($requestData['year']) $this->betYear = $this->getGregorianRangeForJalaliYear($requestData['year']);
        }
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
}
