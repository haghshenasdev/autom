<?php

namespace App\Filament\Resources;

use AlperenErsoy\FilamentExport\Actions\FilamentExportHeaderAction;
use App\Filament\Resources\MinutesResource\Pages;
use App\Filament\Resources\MinutesResource\RelationManagers;
use App\Http\Controllers\BaleBotController;
use App\Models\Minutes;
use App\Models\MinutesGroup;
use App\Models\TaskGroup;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Hugomyb\FilamentMediaAction\Tables\Actions\MediaAction;
use IbrahimBougaoua\FilaProgress\Tables\Columns\ProgressBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class MinutesResource extends Resource
{
    protected static ?string $model = Minutes::class;

    protected static ?string $label = "صورت جلسه";

    protected static ?string $navigationGroup = 'صورت جلسه';


    protected static ?string $pluralModelLabel = "صورت جلسه ها";

    protected static ?string $pluralLabel = "صورت جلسه";

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (!$user->can('restore_any_minutes')) return parent::getEloquentQuery()->where('typer_id',Auth::id());

        return parent::getEloquentQuery();

    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(Minutes::formSchema());
    }

    public static function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('id')->label('ثبت')
                ->searchable()->sortable(),
            TextColumn::make('title')->label('عنوان')
                ->searchable(),
            TextColumn::make('typer.name')->label('نویسنده')->toggleable(),
            TextColumn::make('task_creator.name')->label('جلسه')->words(10)->toggleable(),
            TextColumn::make('organ.name')->label('امضا کنندگان'),
            TextColumn::make('tasks_count')
                ->counts('tasks')
                ->label('تعداد فعالیتها')->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('approves_count')
                ->label('تعداد مصوبه')->sortable()
                ->counts('approves')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('date')->label(' تاریخ')->jalaliDateTime(),
            ProgressBar::make('pb')->label('درصد فعالیت انجام شده')
                ->getStateUsing(function ($record) {
                    $total = $record->tasks()->count();
                    $progress = $record->tasks()->where('completed',true)->count();
                    return [
                        'total' => $total,
                        'progress' => $progress,
                    ];
                })->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('group.name')->label('دسته بندی')->toggleable(isToggledHiddenByDefault: true),
        ];
        if (request()->cookie('mobile_mode') === 'on'){
            $bale = new BaleBotController();
            $columns = [
                Split::make([
                    TextColumn::make('data')
                        ->searchable()->state(fn (Model $record): string => str_replace("\n",'<br>',$bale->createMinuteMessage($record,auth()->user(),false)))->html()
                ])
            ];
        }
        return $table->defaultSort('minutes.id','desc')
            ->columns($columns)
            ->filters([
                Filter::make('tree')
                    ->form([
                        SelectTree::make('group')->label('دسته بندی')
                            ->relationship('group', 'name', 'parent_id')
                            ->independent(false)
                            ->enableBranchNode(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['group'], function ($query, $categories) {
                            return $query->whereHas('group', fn($query) => $query->whereIn('minutes_group_id', $categories));
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['group']) {
                            return null;
                        }

                        return __('group') . ': ' . implode(', ', MinutesGroup::whereIn('id', $data['group'])->get()->pluck('name')->toArray());
                    }),
                Tables\Filters\SelectFilter::make('typer_id')->label('نویسنده')
                    ->relationship('typer', 'name')
                    ->searchable()->preload(),
                Tables\Filters\SelectFilter::make('task_id')->label('جلسه')
                    ->relationship('task_creator', 'name')
                    ->searchable(),
                Tables\Filters\SelectFilter::make('organ_id')->label('امضا کننده')
                    ->relationship('organ', 'name')->multiple()->preload()
                    ->searchable(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('باز کردن لینک')
                    ->label('نمایش فایل')
                    ->url(fn(Model $record) => env('APP_URL').'/appendix-other-show/'.$record->getFilePath(), shouldOpenInNewTab: true)
                    ->visible(fn(Model $record): bool => $record->file !== null)
                    ->color('primary')
                    ->button()
                    ->icon('heroicon-o-arrow-top-right-on-square'),
            ])->headerActions([
                Action::make('print')
                    ->label('چاپ جدول')
                    ->icon('heroicon-o-printer')
                    ->extraAttributes([
                        'onclick' => 'window.print()',
                    ]),
                FilamentExportHeaderAction::make('Export')->label('دریافت خروجی'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                ExportBulkAction::make()->label('دریافت فایل exel'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ApprovesRelationManager::class,
            RelationManagers\TasksRelationManager::class,
            RelationManagers\AppendixOthersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMinutes::route('/'),
            'create' => Pages\CreateMinutes::route('/create'),
            'edit' => Pages\EditMinutes::route('/{record}/edit'),
        ];
    }
}
