<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganTypeResource\Pages;
use App\Filament\Resources\OrganTypeResource\RelationManagers;
use App\Models\OrganType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrganTypeResource extends Resource
{
    protected static ?string $model = OrganType::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()->label('عنوان')
                    ->maxLength(255),
                Forms\Components\TextInput::make('description')
                    ->nullable()->label('توضیحات')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->searchable()->sortable(),
                TextColumn::make('name')->label('عنوان')->searchable(),
                TextColumn::make('description')->label('توضیحات'),
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
            'index' => Pages\ListOrganTypes::route('/'),
            'create' => Pages\CreateOrganType::route('/create'),
            'edit' => Pages\EditOrganType::route('/{record}/edit'),
        ];
    }
}
