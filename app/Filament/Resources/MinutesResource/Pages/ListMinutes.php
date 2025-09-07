<?php

namespace App\Filament\Resources\MinutesResource\Pages;

use App\Filament\Resources\MinutesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMinutes extends ListRecords
{
    protected static string $resource = MinutesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
