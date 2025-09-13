<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TaskResource;
use App\Models\letter;
use App\Models\Task;
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
    protected static ?int $sort = 2;
    protected static ?string $heading= 'آخرین کار ها';


    public function table(Table $table): Table
    {
        return $table
            ->query(
                Task::query()->whereNot('completed',1)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('عنوان'),
                Tables\Columns\IconColumn::make('completed')->label('وضعیت انجام')
                    ->boolean(),
                Tables\Columns\TextColumn::make('started_at_diff')
                    ->label('زمان')
                    ->state(function (Model $record): string {
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
