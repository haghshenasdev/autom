<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskGroupResource\Pages;
use App\Filament\Resources\TaskGroupResource\RelationManagers;
use App\Models\TaskGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaskGroupResource extends Resource
{
    protected static ?string $model = TaskGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $label = "دسته بندی فعالیت ها";

    protected static ?string $navigationGroup = 'دستورکار / فعالیت ها';


    protected static ?string $pluralModelLabel = "دسته بندی فعالیت ها";

    protected static ?string $pluralLabel = "دسته بندی فعالیت ها";

    public static function form(Form $form): Form
    {
        return $form
            ->schema(TaskGroup::formSchema());
    }

    public static function table(Table $table): Table
    {
        $columns = [
            Tables\Columns\TextColumn::make('name')->label('نام')
                ->searchable(),
            Tables\Columns\TextColumn::make('parent.name')->label('گروه اصلی')
                ->numeric()
                ->sortable(),
            Tables\Columns\TextColumn::make('created_at')->label('تاریخ ایجاد')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updated_at')->label('تاریخ ویرایش')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
        if (request()->cookie('mobile_mode') === 'on'){
            $columns = [
                Split::make($columns)->from('md')
            ];
        }
        return $table
            ->columns($columns)
            ->filters([
                //
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                Tables\Actions\BulkAction::make('bulkRelearn')
                    ->label('یادگیری زیر مجموعه ها AI')->configure()
                    ->icon('heroicon-o-arrow-path')->visible(auth()->user()->can('create_ai::words::data'))
                    ->action(function ($records) {
                        $totalWords = 0;
                        $classifier = app(\App\Services\AiKeywordClassifier::class);

                        // استخراج نوع مدل و آیدی‌ها
                        $modelType = \App\Models\TaskGroup::class; // چون این BulkAction در جدول دستورکار‌هاست
                        $modelIds  = collect($records)->pluck('id')->toArray();

                        foreach ($records as $record) {
                            $parentModel = $record;

                            if ($parentModel) {
                                $count = $classifier
                                    ->learn(
                                        $parentModel,
                                        'tasks',   // نام ریلیشن زیرمجموعه
                                        'name',               // فیلد عنوان زیرمجموعه
                                        null,    // فیلد ثانویه مثل شهر
                                        0.5          // درصد حساسیت
                                    );

                                $totalWords += $count;
                            }
                        }

                        // سپس بهینه‌سازی کلمات مشترک
                        $removed = $classifier->optimizeCommonWords($modelType, $modelIds);


                        \Filament\Notifications\Notification::make()
                            ->title("آموزش مجدد انجام شد")
                            ->body("فرایند روی " . count($records) . " رکورد انجام شد و مجموع {$totalWords} کلمه وارد شد." . "\n" . "کلمات مشترک حذف شدند. تعداد {$removed} کلمه پاک شد.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaskGroups::route('/'),
            'create' => Pages\CreateTaskGroup::route('/create'),
            'edit' => Pages\EditTaskGroup::route('/{record}/edit'),
        ];
    }
}
