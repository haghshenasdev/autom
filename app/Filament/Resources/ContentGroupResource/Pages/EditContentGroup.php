<?php

namespace App\Filament\Resources\ContentGroupResource\Pages;

use App\Filament\Resources\ContentGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContentGroup extends EditRecord
{
    protected static string $resource = ContentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
