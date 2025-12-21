<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReferralResource\Pages;
use App\Filament\Resources\ReferralResource\RelationManagers;
use App\Http\Controllers\BaleBotController;
use App\Models\Referral;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class ReferralResource extends Resource
{
    protected static ?string $model = Referral::class;

    protected static ?string $navigationGroup = 'دستور';

    protected static ?string $navigationIcon = 'heroicon-o-paper-airplane';

    protected static ?string $label = "ارجاع";


    protected static ?string $pluralModelLabel = "ارجاع ها";

    protected static ?string $pluralLabel = "ارجاع";

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (!$user->can('restore_any_referral')) return parent::getEloquentQuery()->where('to_user_id',$user->id);

        return parent::getEloquentQuery();

    }

    public static function form(Form $form): Form
    {
        $user = auth()->user();

        return $form
            ->schema([
                Forms\Components\Select::make('letter_id')
                    ->label('نامه')
                    ->relationship('letter', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->subject}")
                    ->disabled(fn (?Model $record) => $record && $record->by_user_id != $user->id)
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\Textarea::make('rule')
                    ->label('دستور')->disabled(fn (?Model $record) => $record && $record->by_user_id != $user->id), //فعال بودن دستور فق برای صاحبش
                Forms\Components\Select::make('by_user_id')
                    ->label('توسط')
                    ->default($user->id)
                    ->disabled(fn (?Model $record) => $record && $record->by_user_id != $user->id) //فعال بودن دستور فق برای صاحبش
                    ->relationship('by_users', 'name')
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\Select::make('to_user_id')
                    ->label('به')
                    ->visible($user->can('restore_any_referral'))
                    ->relationship('users', 'name')
                    ->disabled(fn (?Model $record) => $record && $record->by_user_id != $user->id)
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\Toggle::make('checked')->label('بررسی شده'),
                Forms\Components\Textarea::make('result')
                    ->label('نتیجه')
                    ->maxLength(500),
            ]);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('id')->label('شماره')->sortable(),
            Tables\Columns\TextColumn::make('rule')->label('دستور')->searchable(),
            Tables\Columns\TextColumn::make('result')->label('نتیجه')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('by_users.name')->label('توسط'),
            Tables\Columns\TextColumn::make('users.name')->label('به')->visible(Auth::user()->can('restore_any_referral')),
            Tables\Columns\TextColumn::make('letter_id')->label('شماره نامه')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('letter.subject')->label('موضوع نامه'),
            Tables\Columns\CheckboxColumn::make('checked')->label('بررسی شده'),
            Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->jalaliDateTime()->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updated_at')->label(' تاریخ ویرایش')->jalaliDateTime()->toggleable(isToggledHiddenByDefault: true),
        ];
        if (request()->cookie('mobile_mode') === 'on'){
            $bale = new BaleBotController();
            $columns = [
                Split::make([
                    TextColumn::make('data')
                        ->searchable()->state(fn (Model $record): string => str_replace("\n",'<br>',$bale->CreateReferralMessage($record)))->html(),
                    Tables\Columns\ToggleColumn::make('checked')->tooltip('بررسی شده'),
                ])->from('md')
            ];
        }
        return $table->defaultSort('referrals.id','desc')
            ->columns($columns)
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
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                Action::make('Open')->label('نمایش نامه')
                    ->url(fn (Referral $record): string => LetterResource::getUrl(\auth()->user()->can('update_letter') ? 'edit' : 'view',[$record->letter_id]))
                    ->openUrlInNewTab(),
                Tables\Actions\DeleteAction::make(),
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
//            'edit' => Pages\EditReferral::route('/{record}/edit'),
        ];
    }
}
