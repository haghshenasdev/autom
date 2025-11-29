<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TaskResource\Widgets\TasksGroupsPieChart;
use App\Filament\Resources\TaskResource\Widgets\TasksTrendChart;
use Filament\Pages\Page;

class TasksReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.tasks-report';

    protected static ?string $title = 'گزارش جامع کار ها';

    protected function getHeaderWidgets(): array
    {
        return [
            TasksGroupsPieChart::make(),
            TasksTrendChart::make(),
        ];
    }
}
