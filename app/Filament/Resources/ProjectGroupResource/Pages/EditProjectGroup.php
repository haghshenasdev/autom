<?php

namespace App\Filament\Resources\ProjectGroupResource\Pages;

use App\Filament\Resources\ProjectGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectGroup extends EditRecord
{
    protected static string $resource = ProjectGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
