<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskGroup;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
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

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (!$user->can('restore_any_task')) return parent::getEloquentQuery()->where('Responsible_id',$user->id);

        return parent::getEloquentQuery();

    }

    public static function form(Form $form): Form
    {
        $schema = Task::formSchema();
        $schema[] = Forms\Components\Select::make('project_id')->label('پروژه')
            ->label('پروژه')->multiple()
            ->relationship('project', 'name')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
            ->searchable(['projects.id', 'projects.name'])
            ->preload();

        $schema[] = Forms\Components\Select::make('minutes_id')->label('صورت جلسه')
            ->relationship('minutes', 'title')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->title}")
            ->searchable(['minutes.id', 'minutes.title'])->preload();
        return $form
            ->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id','desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ثبت')
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('عنوان')->words(10)->searchable(),
                Tables\Columns\TextColumn::make('project.name')->label('پروژه')->listWithLineBreaks(),
                Tables\Columns\TextColumn::make('creator.name')->label('ایجاد کننده')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('Responsible.name')->label('مسئول')
                    ->sortable()->visible(auth()->user()->can('restore_any_task'))->visible(auth()->user()->can('restore_any_task')),
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
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => Task::getStatusColor($state))
                    ->state(function (Model $record): string {
                        return Task::getStatusLabel($record->status);})->sortable()->toggleable(isToggledHiddenByDefault: false),
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
                Filter::make('completed')
                    ->label('انجام شده')->query(fn (Builder  $query): Builder  => $query->where('completed', true)),
                Filter::make('no completed')
                    ->label('انجام نشده')->query(fn (Builder  $query): Builder  => $query->where('completed', null)),

                SelectFilter::make('status')->multiple()
                    ->options(Project::getStatusListDefine())->label('وضعیت'),

                Filter::make('tree')
                    ->form([
                        SelectTree::make('group')->label('دسته بندی')
                            ->relationship('group', 'name', 'parent_id')
                            ->independent(false)
                            ->enableBranchNode(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['group'], function ($query, $categories) {
                            return $query->whereHas('group', fn($query) => $query->whereIn('task_groups.id', $categories));
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['group']) {
                            return null;
                        }

                        return __('group') . ': ' . implode(', ', TaskGroup::whereIn('id', $data['group'])->get()->pluck('name')->toArray());
                    }),
                Tables\Filters\SelectFilter::make('project_id')->label('پروژه')
                    ->relationship('project', 'name')
                    ->searchable()->preload()->multiple(),
                Tables\Filters\SelectFilter::make('minutes_id')->label('صورت جلسه')
                    ->relationship('minutes', 'title')
                    ->searchable()->preload(),
                Tables\Filters\SelectFilter::make('city_id')->label('شهر')
                    ->relationship('city', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('Responsible_id')->label('مسئول')
                    ->relationship('responsible', 'name')
                    ->searchable()->preload()->visible(auth()->user()->can('restore_any_task')),
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
            RelationManagers\AppendixOthersRelationManager::class,
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
