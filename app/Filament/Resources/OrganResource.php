<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganResource\Pages;
use App\Filament\Resources\OrganResource\RelationManagers;
use App\Models\Organ;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrganResource extends Resource
{
    protected static ?string $model = Organ::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = "سازمان";


    protected static ?string $pluralModelLabel = "سازمان ها";

    protected static ?string $pluralLabel = "سازمان";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                TextColumn::make('name')->label('عنوان'),
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
            'index' => Pages\ListOrgans::route('/'),
            'create' => Pages\CreateOrgan::route('/create'),
            'edit' => Pages\EditOrgan::route('/{record}/edit'),
        ];
    }
}
