<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\LetterResource;
use App\Filament\Resources\ReferralResource;
use App\Filament\Resources\TaskResource;
use App\Models\letter;
use App\Models\Referral;
use App\Models\Task;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class lastReferralTablewidget extends BaseWidget
{

    protected static ?int $sort = 2;
    protected static ?string $heading= 'آخرین ارجاع ها';

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
                Action::make('Open')->label('نمایش')->iconButton()->icon('heroicon-o-eye')
                    ->url(fn (Referral $record): string => ReferralResource::getUrl('edit',[$record->id]))
                    ->openUrlInNewTab(),
                Action::make('Open')->label('نامه مربوطه')->iconButton()->icon('heroicon-o-envelope')
                    ->url(fn (Referral $record): string => LetterResource::getUrl('edit',[$record->letter()->first()->id]))
                    ->openUrlInNewTab(),
            ]);
    }
}
