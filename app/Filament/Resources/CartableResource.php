<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CartableResource\Pages;
use App\Filament\Resources\CartableResource\RelationManagers;
use App\Models\Cartable;
use App\Models\Referral;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class CartableResource extends Resource
{
    protected static ?string $model = Cartable::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'دستور';


    protected static ?string $label = "کارپوشه";


    protected static ?string $pluralModelLabel = "کارپوشه";

    protected static ?string $pluralLabel = "کارپوشه";

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id',Auth::id());
    }

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
        return $table->defaultSort('id','desc')
            ->columns([
                TextColumn::make('id')->label('ثبت')->sortable(),
                Tables\Columns\TextColumn::make('letter_id')->label('شماره نامه')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('letter.subject')->label('نامه')->searchable(),
                Tables\Columns\TextColumn::make('letter.customers.name')->label('صاحب نامه - شخص'),
                Tables\Columns\TextColumn::make('letter.organs_owner.name')->label('صاحب نامه - ارگان'),
                Tables\Columns\TextColumn::make('letter.created_at')->jalaliDateTime()->label('تاریخ نامه'),
                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->jalaliDateTime()->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ آخرین ویرایش')->jalaliDateTime()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\CheckboxColumn::make('checked')->label('بررسی شده'),
            ])
            ->filters([
                Filter::make('checked')
                    ->label('بررسی شده'),
                Filter::make('no checked')
                    ->label('بررسی نشده')->query(fn (Builder  $query): Builder  => $query->where('checked', false)),

                Filter::make('created_at')
                    ->form([
                        Fieldset::make('تاریخ ویرایش')->schema([
                            DatePicker::make('created_from')->label('از')->jalali(),
                            DatePicker::make('created_until')->label('لغایت')->jalali(),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('updated_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('updated_at', '<=', $date),
                            );
                    })
                ,
            ])
            ->actions([
                Action::make('Open')->label('نمایش نامه')
                    ->url(fn (Cartable $record): string => LetterResource::getUrl(\auth()->user()->can('update_letter') ? 'edit' : 'view',[$record->letter_id]))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->recordUrl(fn ($record) => null) // غیرفعال کردن لینک پیش‌فرض
            ->recordAction(null)
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
