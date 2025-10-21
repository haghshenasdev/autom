<?php

namespace App\Filament\Resources\LetterResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ReferralsRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';

    protected static ?string $label = 'ارجاع';

    protected static ?string $pluralLabel = 'ارجاع';

    protected static ?string $modelLabel = 'ارجاع';

    protected static ?string $title = 'ارجاع ها';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('rule')
                    ->label('دستور')
                    ->maxLength(255),
                Forms\Components\Select::make('to_user_id')
                    ->label('به')
                    ->relationship('users', 'name')
                    ->searchable()
                    ->required()
                    ->preload(),
            ]);
    }



    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('rule')
            ->columns([
                Tables\Columns\TextColumn::make('rule')->label('دستور'),
                Tables\Columns\TextColumn::make('by_users.name')->label('توسط'),
                Tables\Columns\TextColumn::make('users.name')->label('به'),
                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->jalaliDateTime(),
                Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش')->jalaliDateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make('header')
                    ->mutateFormDataUsing(function (array $data): array {

                    $data['by_user_id'] = auth()->id();

                    return $data;
                }),
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
                Tables\Actions\CreateAction::make('empty')->mutateFormDataUsing(function (array $data): array {

                    $data['by_user_id'] = auth()->id();

                    return $data;
                }),
            ]);
    }
}
