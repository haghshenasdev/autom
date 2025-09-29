<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MinutesResource\Pages;
use App\Filament\Resources\MinutesResource\RelationManagers;
use App\Models\Minutes;
use App\Models\MinutesGroup;
use App\Models\TaskGroup;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use IbrahimBougaoua\FilaProgress\Tables\Columns\ProgressBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MinutesResource extends Resource
{
    protected static ?string $model = Minutes::class;

    protected static ?string $label = "صورت جلسه";

    protected static ?string $navigationGroup = 'صورت جلسه';


    protected static ?string $pluralModelLabel = "صورت جلسه ها";

    protected static ?string $pluralLabel = "صورت جلسه";

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(Minutes::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('id','desc')
            ->columns([
                TextColumn::make('id')->label('ثبت')
                    ->searchable()->sortable(),
                TextColumn::make('title')->label('عنوان')
                    ->searchable(),
                TextColumn::make('typer.name')->label('نویسنده')->toggleable(),
                TextColumn::make('task_creator.name')->label('جلسه')->words(10)->toggleable(),
                TextColumn::make('titleholder')->label('امضا کنندگان')
                    ->html()->alignCenter()
                    ->state(function (Model $record): string {
                        $customers = $record->titleholder()->get();
                        $string = '';
                        foreach ($customers as $recordi){
                            $string .= $recordi->name ."-". $recordi->official . " ، ".$recordi->organ()->first('name')->name . "<br>";
                        }
                        return $string;
                    }),
                TextColumn::make('tasks_count')
                    ->counts('tasks')
                    ->label('تعداد کارها')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('approves_count')
                    ->label('تعداد مصوبه')
                    ->counts('approves')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('date')->label(' تاریخ')->jalaliDateTime(),
                ProgressBar::make('pb')->label('درصد کار انجام شده')
                    ->getStateUsing(function ($record) {
                        $total = $record->tasks()->count();
                        $progress = $record->tasks()->where('completed',true)->count();
                        return [
                            'total' => $total,
                            'progress' => $progress,
                        ];
                    })->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('group.name')->label('دسته بندی')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('tree')
                    ->form([
                        SelectTree::make('group')->label('دسته بندی')
                            ->relationship('group', 'name', 'parent_id')
                            ->independent(false)
                            ->enableBranchNode(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['group'], function ($query, $categories) {
                            return $query->whereHas('group', fn($query) => $query->whereIn('minutes_group_id', $categories));
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['group']) {
                            return null;
                        }

                        return __('group') . ': ' . implode(', ', MinutesGroup::whereIn('id', $data['group'])->get()->pluck('name')->toArray());
                    }),
                Tables\Filters\SelectFilter::make('typer_id')->label('نویسنده')
                    ->relationship('typer', 'name')
                    ->searchable()->preload(),
                Tables\Filters\SelectFilter::make('task_id')->label('جلسه')
                    ->relationship('task_creator', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('titleholder_id')->label('امضا کننده')
                    ->relationship('titleholder', 'name')->multiple()->preload()
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ApprovesRelationManager::class,
            RelationManagers\TasksRelationManager::class,
            RelationManagers\AppendixOthersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMinutes::route('/'),
            'create' => Pages\CreateMinutes::route('/create'),
            'edit' => Pages\EditMinutes::route('/{record}/edit'),
        ];
    }
}
