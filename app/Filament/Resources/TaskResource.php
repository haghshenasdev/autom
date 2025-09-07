<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use IbrahimBougaoua\FilaProgress\Tables\Columns\ProgressBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $label = "کار";

    protected static ?string $navigationGroup = 'پروژه / جلسه / پیگیری';


    protected static ?string $pluralModelLabel = "کار ها";

    protected static ?string $pluralLabel = "کار";


    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    public static function form(Form $form): Form
    {
        $schema = Task::formSchema();
        $schema[] = Forms\Components\Select::make('project_id')->label('پروژه')
            ->relationship('project', 'name')
            ->searchable()->preload()->multiple();

        $schema[] = Forms\Components\Select::make('minutes_id')->label('صورت جلسه')
            ->relationship('minutes', 'title')
            ->searchable()->preload();
        return $form
            ->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('عنوان'),
                Tables\Columns\TextColumn::make('project.name')->label('پروژه'),
                Tables\Columns\TextColumn::make('creator.name')->label('ایجاد کننده'),
                Tables\Columns\TextColumn::make('Responsible.name')->label('مسئول')
                    ->sortable(),
                Tables\Columns\IconColumn::make('completed')->label('وضعیت انجام')
                    ->boolean(),
                Tables\Columns\TextColumn::make('completed_at')->label('تاریخ انجام')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('task_group.name')->label('دسته بندی'),
                Tables\Columns\TextColumn::make('started_at')->label('شروع')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('ended_at')->label('پایان')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('ایجاد')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label('بروز رسانی')
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ProgressBar::make('progress')->label('پیشرفت')
                    ->getStateUsing(function ($record) {
                        return [
                            'total' => 100,
                            'progress' => $record->progress,
                        ];
                    })->toggleable(isToggledHiddenByDefault: true)->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                ExportBulkAction::make()->label('دریافت فایل exel'),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
