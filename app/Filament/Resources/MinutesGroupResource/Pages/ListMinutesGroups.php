<?php

namespace App\Filament\Resources\MinutesGroupResource\Pages;

use App\Filament\Resources\MinutesGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMinutesGroups extends ListRecords
{
    protected static string $resource = MinutesGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
