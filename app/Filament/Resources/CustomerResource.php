<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationGroup = 'مراجعه کننده';


    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $label = "مراجعه کننده";


    protected static ?string $pluralModelLabel = "مراجعه کننده ها";

    protected static ?string $pluralLabel = "مراجعه کننده";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('نام و نام خانوادگی')
                    ->maxLength(255),
                Forms\Components\TextInput::make('code_melli')
                    ->required()
                    ->unique()
                    ->numeric()
                    ->label('کد ملی'),
                Forms\Components\DatePicker::make('birth_date')
                    ->label('تاریخ تولد')->jalali()
                ,
                Forms\Components\TextInput::make('phone')
                    ->label('شماره تماس')
                    ->required()
                    ->tel(),
                Forms\Components\Select::make('city_id')
                    ->label('شهر')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->label('نام شهر')
                        ,
                    ])
                ,
            ]);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('id'),
            TextColumn::make('name')->label('نام')->searchable(),
            TextColumn::make('code_melli')->label('کد ملی')->searchable(),
            TextColumn::make('city.name')->label('شهر'),
            TextColumn::make('phone')->label('شماره تماس')->searchable(),
        ];
        if (request()->cookie('mobile_mode') === 'on'){
            $columns = [
                Split::make($columns)->from('md')
            ];
        }
        return $table
            ->columns($columns)
            ->filters([
                SelectFilter::make('city_id')
                    ->label('شهر')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
            ], layout: FiltersLayout::AboveContentCollapsible)
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
            RelationManagers\LettersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
