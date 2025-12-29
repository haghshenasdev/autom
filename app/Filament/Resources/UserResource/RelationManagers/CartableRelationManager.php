<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\CartableResource;
use App\Models\Cartable;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CartableRelationManager extends RelationManager
{
    protected static string $relationship = 'cartable';

    protected static ?string $label = 'کارپوشه';

    protected static ?string $pluralLabel = 'کارپوشه';

    protected static ?string $modelLabel = 'کارپوشه';

    protected static ?string $title = 'کارپوشه';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('letter_id')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return CartableResource::table($table);
        return $table
            ->recordTitleAttribute('letter_id')
            ->columns([
                Tables\Columns\TextColumn::make('letter_id'),
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
            ]);
    }
}
