<?php

namespace App\Filament\Resources\LetterResource\RelationManagers;

use App\Models\letter;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LetterProjectRelationManager extends RelationManager
{
    protected static string $relationship = 'projects';

    protected static ?string $label = 'پروژه ها';

    protected static ?string $pluralLabel = 'پروژه';

    protected static ?string $modelLabel = 'پروژه';

    protected static ?string $title = 'پروژه ها';

    public function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('summary')
            ->columns([
                Tables\Columns\TextColumn::make('project')->state(function (Model $record): string {
                    return Project::find($record->project_id)->name;
                }),
                Tables\Columns\TextColumn::make('summary')->words(10),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
                Tables\Actions\AttachAction::make()
                    ->form([
                        Forms\Components\Select::make('project_id')
                            ->label('انتخاب پروژه')
                            ->options(Project::all()->pluck('name', 'id')->map(fn ($name, $id) => "{$id} - {$name}"))
                            ->searchable()
                            ->required(),
                        Forms\Components\Textarea::make('summary')
                            ->label('خلاصه'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->form([
                    Forms\Components\Select::make('project_id')
                        ->label('انتخاب پروژه')
                        ->options(Project::all()->pluck('name', 'id')->map(fn ($name, $id) => "{$id} - {$name}"))
                        ->searchable()
                        ->required(),
                    Forms\Components\Textarea::make('summary')
                        ->label('خلاصه'),
                ]),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
