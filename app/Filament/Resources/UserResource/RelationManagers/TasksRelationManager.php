<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\TaskResource;
use App\Filament\Widgets\LatestTasks;
use App\Models\Letter;
use App\Models\Task;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'task_responsible';

    protected static ?string $label = 'فعالیت';

    protected static ?string $pluralLabel = 'فعالیت';

    protected static ?string $modelLabel = 'فعالیت';

    protected static ?string $title = 'فعالیت ها';

    public function form(Form $form): Form
    {
        return $form
            ->schema(Task::formSchema());
    }

    public function table(Table $table): Table
    {
        $columns = [
            Tables\Columns\TextColumn::make('name')->label('عنوان'),
            Tables\Columns\IconColumn::make('completed')->label('وضعیت انجام')
                ->boolean()->sortable(),
            Tables\Columns\TextColumn::make('ended_at')
                ->label('زمان اتمام')->sortable()
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
                    if (is_null($record->ended_at)) return '';
                    $diff = Carbon::now()->diffInDays(Carbon::parse($record->ended_at), false);

                    if ($diff < 0) {
                        return 'danger'; // قرمز
                    } elseif ($diff === 0) {
                        return 'success'; // سبز
                    } else {
                        return 'info'; // آبی
                    }
                }),
            Tables\Columns\TextColumn::make('started_at')
                ->label('زمان شروع')->sortable()
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
                    if (is_null($record->started_at)) return '';
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
        ];

        if (request()->cookie('mobile_mode') === 'on'){
            $columns = [
                // استفاده از حالت split برای زمان اتمام و زمان شروع
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('name')->label('عنوان')->state(fn (Model $record): string => ($record->completed ? '✅' : '❌') . ' ' . $record->name),

                    Tables\Columns\TextColumn::make('ended_at')->sortable()->label('زمان اتمام')
                        ->description('زمان اتمام', position: 'above')
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
                            if (is_null($record->ended_at)) return '';
                            $diff = Carbon::now()->diffInDays(Carbon::parse($record->ended_at), false);

                            if ($diff < 0) {
                                return 'danger'; // قرمز
                            } elseif ($diff === 0) {
                                return 'success'; // سبز
                            } else {
                                return 'info'; // آبی
                            }
                        }),

                    Tables\Columns\TextColumn::make('started_at')->sortable()->label('زمان شروع')
                        ->description('زمان شروع', position: 'above')
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
                            if (is_null($record->started_at)) return '';
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
                ])->from('md'),
            ];
        }

        $today = \Carbon\Carbon::today();
        return $table
            ->query(
                Task::query()->where(function ($q) {
                    $q->whereNull('completed')
                        ->orWhere('completed', 0);
                })->where('Responsible_id',$this->ownerRecord->id)
                    ->orderByRaw("CASE
                WHEN ended_at IS NULL THEN 3
            WHEN DATE(ended_at) = ? THEN 0
            WHEN DATE(ended_at) < ? THEN 2
            ELSE 1 END", [$today, $today])
                    ->orderBy('ended_at', 'asc')
            )
            ->columns($columns)->emptyStateHeading('هیچ موردی یافت نشد')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Action::make('Open')->label('نمایش')
                    ->url(fn (Task $record): string => TaskResource::getUrl('edit',[$record->id]))
                    ->openUrlInNewTab(),
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
