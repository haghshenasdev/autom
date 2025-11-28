<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TaskResource;
use App\Models\Letter;
use App\Models\Task;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

class LatestTasks extends BaseWidget
{

    use HasWidgetShield;

    protected static ?int $sort = 2;
    protected static ?string $heading= 'آخرین کار ها';


    public function table(Table $table): Table
    {
        $today = \Carbon\Carbon::today();
        return $table
            ->query(
                Task::query()->whereNot('completed',1)->orderByRaw("CASE
            WHEN DATE(ended_at) = ? THEN 0
            WHEN DATE(ended_at) < ? THEN 2
            ELSE 1 END", [$today, $today])
                    ->orderBy('ended_at', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('عنوان'),
                Tables\Columns\IconColumn::make('completed')->label('وضعیت انجام')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ended_at_diff')
                    ->label('زمان اتمام')
                    ->state(function (Model $record): string {
                        if (is_null($record->ended_at)) return 'نامشخص';
                        $diff = Carbon::now()->diffInDays(Carbon::parse($record->ended_at), false);

                        if ($diff < 0) {
                            return abs($diff) . ' روز گذشته';
                        } elseif ($diff === 0) {
                            return 'امروز';
                        } else {
                            return abs($diff) . ' روز مانده';
                        }
                    })
                    ->color(function (Model $record): string {
                        $diff = Carbon::now()->diffInDays(Carbon::parse($record->ended_at), false);

                        if ($diff < 0) {
                            return 'danger'; // قرمز
                        } elseif ($diff === 0) {
                            return 'success'; // سبز
                        } else {
                            return 'info'; // آبی
                        }
                    }),
                Tables\Columns\TextColumn::make('started_at_diff')
                    ->label('زمان شروع')
                    ->state(function (Model $record): string {
                        if (is_null($record->started_at)) return 'نامشخص';
                        $diff = Carbon::parse($record->started_at)->diffInDays(Carbon::now(), false);

                        if ($diff < 0) {
                            return abs($diff) . ' روز مانده';
                        } elseif ($diff === 0) {
                            return 'امروز';
                        } else {
                            return $diff . ' روز گذشته';
                        }
                    })
                    ->color(function (Model $record): string {
                        $diff = Carbon::parse($record->started_at)->diffInDays(Carbon::now(), false);

                        if ($diff < 0) {
                            return 'info'; // آبی
                        } elseif ($diff === 0) {
                            return 'success'; // سبز
                        } else {
                            return 'danger'; // قرمز
                        }
                    }),
                Tables\Columns\TextColumn::make('task_group.name')->label('دسته بندی'),
            ])->actions([
                Action::make('Open')->label('نمایش')
                    ->url(fn (Task $record): string => TaskResource::getUrl('edit',[$record->id]))
                    ->openUrlInNewTab(),
            ])->emptyStateHeading('هیچ موردی یافت نشد');
    }
}
