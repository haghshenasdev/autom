<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferralResource\Pages;
use App\Filament\Resources\ReferralResource\RelationManagers;
use App\Models\Referral;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReferralResource extends Resource
{
    protected static ?string $model = Referral::class;

    protected static ?string $navigationGroup = 'دستور';

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $label = "ارجاع";


    protected static ?string $pluralModelLabel = "ارجاع ها";

    protected static ?string $pluralLabel = "ارجاع";

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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),
                Tables\Columns\TextColumn::make('rule')->label('دستور'),
                Tables\Columns\TextColumn::make('letter_id')->label('نامه'),
                TextColumn::make('by_user_id')->label('توسط')
                    ->state(function (Model $record): string {
                        return $record->by_users()->first('name')->name;
                    }),
                Tables\Columns\TextColumn::make('to_user_id.name')->label('به')
                    ->state(function (Model $record): string {
                        return $record->users()->first('name')->name;
                    })
                ,
                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد'),
                Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش'),
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
                Tables\Actions\CreateAction::make()->mutateFormDataUsing(function (array $data): array {

                    $data['by_user_id'] = auth()->id();

                    return $data;
                }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    //bugg
    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['by_user_id'] = auth()->id();

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReferrals::route('/'),
            'create' => Pages\CreateReferral::route('/create'),
            'edit' => Pages\EditReferral::route('/{record}/edit'),
        ];
    }
}
