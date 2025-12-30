<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Pages\TasksReport;
use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('report')->label('گزارش فعالیت ها')->url(TasksReport::getUrl())->outlined()->icon('heroicon-o-chart-pie'),

        ];
    }
}
