<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReplicationResource\Pages;
use App\Filament\Resources\ReplicationResource\RelationManagers;
use App\Models\Replication;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReplicationResource extends Resource
{
    protected static ?string $model = Replication::class;

    protected static ?string $navigationGroup = 'دبیرخانه';

    protected static ?int $navigationSort = 3;


    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $label = "رونوشت";


    protected static ?string $pluralModelLabel = "رونوشت ها";

    protected static ?string $pluralLabel = "رونوشت";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('letter_id')
                    ->label('نامه')
                    ->relationship('letter', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->subject}")
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\Select::make('titleholder_id')
                    ->label('گیرنده نامه')
                    ->relationship('titleholder', 'name')
                    ->searchable()
                    ->required()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('titleholder.name')->label('گیرنده'),
                TextColumn::make('letter.id')->label('شماره نامه'),
                TextColumn::make('letter.subject')->label('موضوع نامه'),
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
            'index' => Pages\ListReplications::route('/'),
            'create' => Pages\CreateReplication::route('/create'),
            'edit' => Pages\EditReplication::route('/{record}/edit'),
        ];
    }
}
