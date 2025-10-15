<?php

namespace App\Filament\Resources\MinutesResource\Pages;

use App\Filament\Resources\MinutesResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\File;

class EditMinutes extends EditRecord
{
    protected static string $resource = MinutesResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['file'] = $this->record->getFilePath();
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!is_null($data['file']) && $data['file'] == $this->record->getFilePath()){
            $data['file'] = File::extension($data['file']);
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
