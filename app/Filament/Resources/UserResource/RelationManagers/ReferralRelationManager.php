<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\ReferralResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReferralRelationManager extends RelationManager
{
    protected static string $relationship = 'referral';

    protected static ?string $label = 'ارجاع';

    protected static ?string $pluralLabel = 'ارجاع';

    protected static ?string $modelLabel = 'ارجاع';

    protected static ?string $title = 'ارجاعات';

    public function form(Form $form): Form
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
                Forms\Components\Textarea::make('rule')
                    ->label('دستور'),
                Forms\Components\Select::make('by_user_id')
                    ->label('توسط')
                    ->default(auth()->id())
                    ->relationship('by_users', 'name')
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\Select::make('to_user_id')
                    ->label('به')
                    ->relationship('users', 'name')
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\Toggle::make('checked')->label('بررسی شده'),
                Forms\Components\Textarea::make('result')
                    ->label('نتیجه')
                    ->maxLength(500),
            ]);
    }

    public function table(Table $table): Table
    {
        return ReferralResource::table($table);
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
