<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Pages\UsersReport;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('report')->label('گزارش فعالیت ها')->url(UsersReport::getUrl())->outlined()->icon('heroicon-o-chart-pie'),
        ];
    }
}
