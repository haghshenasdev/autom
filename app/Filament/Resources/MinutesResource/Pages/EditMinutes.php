<?php

namespace App\Filament\Resources\MinutesResource\Pages;

use App\Filament\Resources\MinutesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMinutes extends EditRecord
{
    protected static string $resource = MinutesResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['file'] = $this->record->getFilePath();
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
