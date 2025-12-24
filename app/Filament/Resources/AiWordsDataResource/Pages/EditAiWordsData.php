<?php

namespace App\Filament\Resources\AiWordsDataResource\Pages;

use App\Filament\Resources\AiWordsDataResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAiWordsData extends EditRecord
{
    protected static string $resource = AiWordsDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
