<?php

namespace App\Filament\Resources\ContentGroupResource\Pages;

use App\Filament\Resources\ContentGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContentGroups extends ListRecords
{
    protected static string $resource = ContentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
