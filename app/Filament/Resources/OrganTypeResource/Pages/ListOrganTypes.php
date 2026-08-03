<?php

namespace App\Filament\Resources\OrganTypeResource\Pages;

use App\Filament\Resources\OrganTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrganTypes extends ListRecords
{
    protected static string $resource = OrganTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
