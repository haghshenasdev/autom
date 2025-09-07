<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

class LatestTasks extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading= 'آخرین کار ها';


    public function table(Table $table): Table
    {
        return $table
            ->query(
                Task::query()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('عنوان'),
                Tables\Columns\IconColumn::make('completed')->label('وضعیت انجام')
                    ->boolean(),
                Tables\Columns\TextColumn::make('started_at')->label('شروع')
                    ->jalaliDateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('task_group.name')->label('دسته بندی'),
            ])->actions([
                Action::make('Open')->label('نمایش')
                    ->url(fn (Task $record): string => TaskResource::getUrl('edit',[$record->id]))
                    ->openUrlInNewTab(),
            ]);
    }
}
