<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CartableResource\Pages;
use App\Filament\Resources\CartableResource\RelationManagers;
use App\Models\Cartable;
use App\Models\Referral;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CartableResource extends Resource
{
    protected static ?string $model = Referral::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'دستور';


    protected static ?string $label = "کارپوشه";


    protected static ?string $pluralModelLabel = "کارپوشه";

    protected static ?string $pluralLabel = "کارپوشه";

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
            ])->disabled();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Referral::where('to_user_id',auth()->id()))
            ->columns([

                TextColumn::make('id'),
                Tables\Columns\TextColumn::make('rule')->label('دستور'),
                Tables\Columns\TextColumn::make('letter_id')->label('نامه'),
                TextColumn::make('by_user_id')->label('توسط')
                    ->state(function (Model $record): string {
                        return $record->by_users()->first('name')->name; /// درست بودن رابطه چک شود
                    }),
                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد'),
                Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش'),

                TextColumn::make('id'),
                Tables\Columns\CheckboxColumn::make('checked')->label('بررسی شده'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('Open')->label('نمایش نامه')
                    ->url(fn (Referral $record): string => LetterResource::getUrl('edit',[$record->letter()->first('id')->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
//                Tables\Actions\CreateAction::make(),
            ]);
    }



    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCartables::route('/'),
//            'create' => Pages\CreateCartable::route('/create'),
            'edit' => Pages\EditCartable::route('/{record}/edit'),
        ];
    }
}
