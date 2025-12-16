<?php

namespace App\Filament\Resources\CityResource\RelationManagers;

use App\Filament\Resources\CustomerResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomersRelationManager extends RelationManager
{
    protected static string $relationship = 'customers';

    protected static ?string $label = 'مراجعه کننده';

    protected static ?string $pluralLabel = 'مراجعه کننده';

    protected static ?string $modelLabel = 'مراجعه کننده';

    protected static ?string $title = 'مراجعه کننده ها';

    public function form(Form $form): Form
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

    public function table(Table $table): Table
    {
        return CustomerResource::table($table);
//        return $table
//            ->recordTitleAttribute('name')
//            ->columns([
//                TextColumn::make('id'),
//                TextColumn::make('name')->label('نام'),
//                TextColumn::make('code_melli')->label('کد ملی'),
//                TextColumn::make('city.name')->label('شهر'),
//                TextColumn::make('phone')->label('شماره تماس'),
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
//            ])
//            ->emptyStateActions([
//                Tables\Actions\CreateAction::make(),
//            ]);
    }
}
