<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $label = 'کار';

    protected static ?string $pluralLabel = 'کار';

    protected static ?string $modelLabel = 'کار';

    protected static ?string $title = 'کار ها';

    public function form(Form $form): Form
    {
        return $form
            ->schema(Task::formSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('عنوان'),
                Tables\Columns\CheckboxColumn::make('completed')->label('وضعیت انجام'),
                Tables\Columns\TextColumn::make('completed_at')->label('تاریخ انجام')->jalaliDateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }
}
