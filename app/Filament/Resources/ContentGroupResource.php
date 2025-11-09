<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentGroupResource\Pages;
use App\Filament\Resources\ContentGroupResource\RelationManagers;
use App\Models\ContentGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContentGroupResource extends Resource
{
    protected static ?string $model = ContentGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $label = "دسته بندی اسناد";

    protected static ?string $navigationGroup = 'اسناد';

    protected static ?int $navigationSort = 2;

    protected static ?string $pluralModelLabel = "دسته بندی اسناد";

    protected static ?string $pluralLabel = "دسته بندی اسناد";

    public static function form(Form $form): Form
    {
        return $form
            ->schema(ContentGroup::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('شماره')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')->label('نام')
                    ->searchable(),
                Tables\Columns\TextColumn::make('parent.name')->label('گروه اصلی')
                    ->numeric()
                    ->sortable(),
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
            RelationManagers\ContentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContentGroups::route('/'),
            'create' => Pages\CreateContentGroup::route('/create'),
            'edit' => Pages\EditContentGroup::route('/{record}/edit'),
        ];
    }
}
