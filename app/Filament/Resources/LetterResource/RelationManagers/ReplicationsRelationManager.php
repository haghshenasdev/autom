<?php

namespace App\Filament\Resources\LetterResource\RelationManagers;

use App\Models\Organ;
use App\Models\OrganType;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'replications';

    protected static ?string $label = 'رونوشت';

    protected static ?string $pluralLabel = 'رونوشت';

    protected static ?string $modelLabel = 'رونوشت';

    protected static ?string $title = 'رونوشت ها';


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('organ')
                    ->label('به')->required()
                    ->relationship('organ', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                    ->prefixActions([
                        Action::make('updateAuthor')
                            ->icon('heroicon-o-arrows-pointing-out')
                            ->label('انتخاب بر اساس نوع')
                            ->action(function (array $data,Set $set): void {
                                $set('organ', $data['organ_selected']);
                            })
                            ->form([
                                Select::make('organ_type_id')
                                    ->label('نوع')
                                    ->options(OrganType::query()->pluck('name', 'id'))
                                    ->live()
                                    ->searchable()
                                    ->required(),
                                Select::make('organ_selected')
                                    ->label('ارگان')
                                    ->options(fn (Get $get) => $get('organ_type_id')
                                        ? Organ::where('organ_type_id', $get('organ_type_id'))->pluck('name', 'id')
                                        : [])
                                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                    ->searchable()
                                    ->preload()
                            ])
                    ])
                    ->searchable()
                    ->preload(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organ.name')->label('گیرنده'),
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
