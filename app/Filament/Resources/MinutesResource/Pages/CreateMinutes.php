<?php

namespace App\Filament\Resources\MinutesResource\Pages;

use App\Filament\Resources\MinutesResource;
use App\Http\Controllers\ai\MinutesParser;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateMinutes extends CreateRecord
{
    protected static string $resource = MinutesResource::class;

    public function getHeaderActions(): array
    {
        return [
            Action::make('parseText')
                ->label('پردازش متن')
                ->modalHeading('تکمیل فرم از طریق پردازش متن نامه')
                ->modalButton('بارگذاری در فرم')
                ->form([
                    Textarea::make('caption')
                        ->label('متن نامه')
                        ->rows(10)
                        ->required(),
                ])
                ->action(function (array $data, $livewire) {
                    $caption = $data['caption'];
                    $minu = new MinutesParser();
                    $dataMinute = $minu->parse($caption);

                    // پر کردن فرم زیرین
                    $livewire->form->fill([
                        'title' => $dataMinute['title'],
                        'date' => $dataMinute['title_date'] ?? Carbon::now(),
                        'description' => $caption,
                        'organ' => $dataMinute['organs'],
                        'task_id' => $dataMinute['task_id'],
                    ]);

                    Notification::make()
                        ->title('پردازش در فرم بارگزاری شد')
                        ->success()
                        ->send();
                }),
        ];
    }
}
