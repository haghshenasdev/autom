<?php

namespace App\Filament\Resources\AiWordsDataResource\Pages;

use App\Filament\Resources\AiWordsDataResource;
use App\Services\AiKeywordClassifier;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListAiWordsData extends ListRecords
{
    protected static string $resource = AiWordsDataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Action::make('testClassify')
                ->label('تست دسته‌بندی')
                ->icon('heroicon-o-question-mark-circle')
                ->modalHeading('تست دسته‌بندی عنوان')
                ->modalButton('تشخیص بده')
                ->form([
                    TextInput::make('title')
                        ->label('عنوان')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $classifier = app(AiKeywordClassifier::class);
                    $results = $classifier->classify($data['title'], 0.1);

                    $list = collect($results)->map(fn($r) =>
                    "مدل: {$r['model_type']} - شناسه: {$r['model_id']} - درصد: {$r['percent']}%"
                    )->implode("\n");

                    if (empty($list)) {
                        $list = "هیچ دسته‌بندی مرتبطی یافت نشد.";
                    }

                    \Filament\Notifications\Notification::make()
                        ->title('نتیجه تست دسته‌بندی')
                        ->body($list)
                        ->success()
                        ->send();
                }),
        ];
    }
}
