<?php

namespace App\Filament\Resources\MinutesResource\RelationManagers;

use App\Filament\Resources\TaskResource;
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
        $schema = Task::formSchema();
        $schema[] = Forms\Components\Select::make('project_id')->label('دستورکار')
            ->relationship('project', 'name')
            ->searchable()->preload()->multiple();
        return $form
            ->schema($schema);
    }

    public function table(Table $table): Table
    {
        return TaskResource::table($table);
//        return $table
//            ->recordTitleAttribute('name')
//            ->columns([
//                Tables\Columns\TextColumn::make('name')->label('عنوان'),
//                Tables\Columns\TextColumn::make('Responsible.name')->label('مسئول'),
//                Tables\Columns\CheckboxColumn::make('completed')->label('وضعیت انجام'),
//                Tables\Columns\TextColumn::make('completed_at')->label('تاریخ انجام')->jalaliDateTime(),
//            ])
//            ->filters([
//                //
//            ])
//            ->headerActions([
//                Tables\Actions\CreateAction::make(),
//            ])
//            ->actions([
//                Tables\Actions\EditAction::make(),
//                Tables\Actions\DeleteAction::make(),
//            ])
//            ->bulkActions([
//                Tables\Actions\BulkActionGroup::make([
//                    Tables\Actions\DeleteBulkAction::make(),
//                ]),
//            ]);
    }
}
