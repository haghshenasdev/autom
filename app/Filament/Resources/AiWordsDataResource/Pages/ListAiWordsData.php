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
                TextInput::make('sensitivity')
                    ->label('حساسیت (بین 0 و 1)')
                    ->numeric()
                    ->step('0.1')
                    ->default(0.1)
                    ->required(),
            ])
            ->action(function (array $data, Action $action) {
                $classifier = app(\App\Services\AiKeywordClassifier::class);
                $results = $classifier->classify($data['title'], (float)$data['sensitivity'],limitPerType : 5);

                $list = collect($results)->map(function ($group, $modelType) {
                    return collect($group)->map(function ($r) use ($modelType) {
                        // پیدا کردن مدل مربوط
                        $model = $modelType::find($r['model_id']);
                        $modelTitle = $model?->title ?? $model?->name ?? '---';

                        return "عنوان: {$modelTitle} - مدل: {$modelType} - شناسه: {$r['model_id']} - درصد: {$r['percent']}%";
                    })->implode("<br>");
                })->implode("<hr>");


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
