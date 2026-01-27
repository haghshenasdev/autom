<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Pages\ProjectsReport;
use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('report')->label('گزارش دستورکار ها')->url(ProjectsReport::getUrl())->outlined()->icon('heroicon-o-chart-pie'),
        ];
    }
}
