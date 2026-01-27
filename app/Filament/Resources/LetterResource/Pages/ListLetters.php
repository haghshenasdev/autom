<?php

namespace App\Filament\Resources\LetterResource\Pages;

use App\Filament\Pages\LettersReport;
use App\Filament\Resources\LetterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLetters extends ListRecords
{
    protected static string $resource = LetterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('report')->label('گزارش نامه ها')->url(LettersReport::getUrl())->outlined()->icon('heroicon-o-chart-pie'),
        ];
    }
}
