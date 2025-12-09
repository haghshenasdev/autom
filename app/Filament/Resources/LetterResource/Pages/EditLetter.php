<?php

namespace App\Filament\Resources\LetterResource\Pages;

use App\Filament\Resources\LetterResource;
use App\Models\Letter;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\File;

class EditLetter extends EditRecord
{
    protected static string $resource = LetterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Action::make('timeline')
                ->label('تایم‌لاین')
                ->icon('heroicon-o-clock')
                ->modalHeading('تایم‌لاین نامه')
                ->modalContent(fn (Letter $record) => view('filament.components.timeline-modal', [
                    'events' => $record->timeline(),
                ]))
                ->modalWidth('xl')->modalSubmitAction(false),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!is_null($data['file'])){
            $data['file'] = str_replace($this->record->id.'.','',File::basename($data['file']));
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {

        $data['file'] = $this->record->getFilePath();

        return $data;
    }
}
