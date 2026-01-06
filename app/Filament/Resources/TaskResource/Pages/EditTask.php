<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Services\AiKeywordClassifier;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('ai_classify')
                ->label('دسته بندی و تعیین دستورکار AI')
                ->icon('heroicon-o-sparkles')
                ->form([
                    Select::make('selected_result')
                        ->label('نتایج دسته‌بندی')
                        ->options(function ($record) {
                            $classifier = app(\App\Services\AiKeywordClassifier::class);
                            $results = $classifier->classify($record->name, 0.1, null, null, 5);

                            $options = [];
                            foreach ($results as $modelType => $group) {
                                foreach ($group as $r) {
                                    $model = $modelType::find($r['model_id']);
                                    $modelTitle = $model?->title ?? $model?->name ?? '---';

                                    $key = $modelType . '|' . $r['model_id'];
                                    $options[$key] = "عنوان: {$modelTitle} - مدل: {$modelType} - درصد: {$r['percent']}%";
                                }
                            }

                            return $options;
                        })
                        ->searchable()
                        ->multiple()
                        ->required(),
                ])
                ->action(function ($data, $livewire) {
                    dd($data);
                    if (!empty($data['selected_result'])) {
                        // چون multiple هست، آرایه برمی‌گردد
                        foreach ($data['selected_result'] as $selected) {
                            [$modelType, $modelId] = explode('|', $selected);

                            if ($modelType === \App\Models\Project::class) {
                                // فقط روی فرم ست شود
                                $livewire->form->fill([
                                    'project_id' => $modelId,
                                ]);
                            } elseif ($modelType === \App\Models\TaskGroup::class) {
                                $livewire->form->fill([
                                    'task_group_id' => $modelId,
                                ]);
                            }
                        }

                        Notification::make()
                            ->title('دسته‌بندی AI اعمال شد')
                            ->body("نتیجه انتخابی روی فرم ست شد (ذخیره نشد).")
                            ->success()
                            ->send();
                    }
                })
        ];
    }
}
