<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Filament\Resources\CityResource\RelationManagers;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $navigationGroup = 'مراجعه کننده';


    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $label = "شهر";


    protected static ?string $pluralModelLabel = "شهر ها";

    protected static ?string $pluralLabel = "شهر";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('نام شهر')
                ,
                Select::make('parent_id')->label('زیر مجموعه')
                    ->relationship('parent', 'name')
                    ->searchable()->preload()
            ]);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('id'),
            TextColumn::make('name')->label('نام')->searchable(),
            TextColumn::make('parent.name')->label('شهرستان')->searchable(),
        ];
        if (request()->cookie('mobile_mode') === 'on'){
            $columns = [
                Split::make($columns)->from('md')
            ];
        }
        return $table
            ->columns($columns)
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
            RelationManagers\CustomersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCities::route('/'),
//            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
