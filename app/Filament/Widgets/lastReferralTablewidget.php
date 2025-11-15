<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LetterResource;
use App\Filament\Resources\ReferralResource;
use App\Filament\Resources\TaskResource;
use App\Models\Letter;
use App\Models\Referral;
use App\Models\Task;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class lastReferralTablewidget extends BaseWidget
{

    use HasWidgetShield;

    protected static ?int $sort = 2;
    protected static ?string $heading= 'آخرین ارجاع ها';

    public static function form(Form $form): Form
    {
        $user = auth()->user();

        return $form
            ->schema([
                Forms\Components\Toggle::make('checked')->label('بررسی شده'),
                Forms\Components\Select::make('letter_id')
                    ->label('نامه')
                    ->relationship('letter', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->subject}")
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\Textarea::make('rule')
                    ->label('دستور')->disabled(fn (?Model $record) => $record && $record->by_user_id != $user->id), //فعال بودن دستور فق برای صاحبش
                Forms\Components\Textarea::make('result')
                    ->label('نتیجه')
                    ->maxLength(500),
                Forms\Components\Select::make('by_user_id')
                    ->label('توسط')
                    ->default($user->id)
                    ->relationship('by_users', 'name')
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\Select::make('to_user_id')
                    ->label('به')
                    ->visible($user->can('restore_any_referral'))
                    ->relationship('users', 'name')
                    ->searchable()
                    ->required()
                    ->preload(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Referral::query()->where('to_user_id', auth()->id())
            )
            ->columns([
                Tables\Columns\TextColumn::make('letter.subject')->label('نامه'),
                Tables\Columns\TextColumn::make('rule')->label('دستور'),

                TextColumn::make('by_user_id')->label('توسط')
                    ->state(function (Model $record): string {
                        return $record->by_users()->first('name')->name;
                    }),
                Tables\Columns\TextColumn::make('created_at')->label(' تاریخ ایجاد')->jalaliDateTime(),
                Tables\Columns\CheckboxColumn::make('checked')->label('بررسی'),

            ])->actions([
//                Action::make('Open')->label('نمایش')->iconButton()->icon('heroicon-o-eye')
//                    ->url(fn (Referral $record): string => ReferralResource::getUrl('edit',[$record->id]))
//                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Action::make('Open')->label('نامه مربوطه')->iconButton()->icon('heroicon-o-envelope')
                    ->url(fn (Referral $record): string => LetterResource::getUrl('edit',[$record->letter()->first()->id]))
                    ->openUrlInNewTab(),
            ])->emptyStateHeading('هیچ موردی یافت نشد');
    }
}
