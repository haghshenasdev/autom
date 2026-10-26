<?php

namespace App\Filament\Resources;

use AlperenErsoy\FilamentExport\Actions\FilamentExportHeaderAction;
use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Organ;
use App\Models\OrganType;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\Referral;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use IbrahimBougaoua\FilaProgress\Tables\Columns\ProgressBar;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $label = "دستورکار";

    protected static ?string $navigationGroup = 'دستورکار / فعالیت ها';


    protected static ?string $pluralModelLabel = "دستورکار ها";

    protected static ?string $pluralLabel = "دستورکار";


    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (!$user->can('restore_any_project')) return parent::getEloquentQuery()->where('user_id',$user->id);

        return parent::getEloquentQuery();

    }

    public static function form(Form $form): Form
    {
        return $form->schema(Project::formSchema());
    }

    public static function table(Table $table): Table
    {
        $columns = [];
        if (request()->cookie('mobile_mode') === 'on'){
            $columns = [
                Split::make([
                    Tables\Columns\TextColumn::make('id')->prefix('🆔 ثبت : ')->label('ثبت')
                        ->searchable()->sortable(),
                    Tables\Columns\TextColumn::make('name')->prefix("🎚️ عنوان: ")
                        ->weight(FontWeight::Bold)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('user.name')->prefix("👤 مسئول: ")->visible(auth()->user()->can('restore_any_project')),
                    Tables\Columns\TextColumn::make('city.name')->prefix("🏙️ شهر: ")->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('organ.name')->prefix("🏢 دستگاه اجرایی: ")->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('status')
                        ->prefix('📊 وضعیت: ')->label('وضعیت')
                        ->badge()
                        ->color(fn (string $state): string => Project::getStatusColor($state))
                        ->state(function (Model $record): string {
                            return Project::getStatusLabel($record->status);})->sortable()->toggleable(isToggledHiddenByDefault: false),
                    Tables\Columns\TextColumn::make('amount')->prefix('💰 اعتبار: ')->label('اعتبار')->toggleable()->sortable()->numeric()->suffix('ریال'),
                    Tables\Columns\TextColumn::make('created_at')->prefix("📅 ایجاد: ")
                        ->jalaliDateTime()
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('updated_at')->prefix("📅 بروزرسانی: ")
                        ->dateTime()
                        ->sortable()->jalaliDateTime()
                        ->toggleable(isToggledHiddenByDefault: true),
                    Tables\Columns\TextColumn::make('group.name')->prefix('📚 دسته بندی: '),
                    Tables\Columns\TextColumn::make('tasks_count')
                        ->counts('tasks')->sortable()
                        ->prefix('🧾 تعداد کارها: ')
                        ->toggleable(isToggledHiddenByDefault: true),
                    ProgressBar::make('پیشرفت')
                        ->getStateUsing(function ($record) {
                            $total = $record->required_amount != null ? $record->required_amount : $record->tasks()->count();
                            $progress = $record->tasks()->where('completed',true)->count();
                            return [
                                'total' => $total,
                                'progress' => $progress,
                            ];
                        }),
                ])->from('md')
            ];
        }else{
            $columns = [
                Tables\Columns\TextColumn::make('id')->label('ثبت')
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label("عنوان")
                    ->weight(FontWeight::Bold)
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')->label("توضیحات")
                    ->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')->label("مسئول")->visible(auth()->user()->can('restore_any_project')),
                Tables\Columns\TextColumn::make('city.name')->label("شهر")->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('organ.name')->label("دستگاه اجرایی")->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => Project::getStatusColor($state))
                    ->state(function (Model $record): string {
                        return Project::getStatusLabel($record->status);})->sortable()->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('amount')->label('اعتبار')->toggleable()->sortable()->numeric()->suffix('ریال'),
                Tables\Columns\TextColumn::make('created_at')->label("ایجاد")
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label("تغییر")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('group.name')->label('دسته بندی'),
                Tables\Columns\TextColumn::make('tasks_count')
                    ->counts('tasks')->sortable()
                    ->label('تعداد کارها')
                    ->toggleable(isToggledHiddenByDefault: true),
                ProgressBar::make('پیشرفت')
                    ->getStateUsing(function ($record) {
                        $total = $record->required_amount != null ? $record->required_amount : $record->tasks()->count();
                        $progress = $record->tasks()->where('completed',true)->count();
                        return [
                            'total' => $total,
                            'progress' => $progress,
                        ];
                    }),
            ];
        }
        return $table
            ->columns($columns)
            ->filters([
                Filter::make('tree')->label('دسته بندی')
                    ->form([
                        SelectTree::make('group')->label('دسته بندی')
                            ->relationship('group', 'name', 'parent_id')
                            ->independent(false)
                            ->enableBranchNode(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['group'], function ($query, $categories) {
                            return $query->whereHas('group', fn($query) => $query->whereIn('project_groups.id', $categories));
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['group']) {
                            return null;
                        }

                        return __('group') . ': ' . implode(', ', ProjectGroup::whereIn('id', $data['group'])->get()->pluck('name')->toArray());
                    }),
                SelectFilter::make('city_id')
                    ->label('شهر')->multiple()->preload()
                    ->relationship('city','name'),
                SelectFilter::make('organ_id')
                    ->label('اداره')->multiple()->preload()
                    ->relationship('organ','name'),
                SelectFilter::make('status')->multiple()
                    ->options(Project::getStatusListDefine())->label('وضعیت'),
                SelectFilter::make('user_id')
                    ->label('مسئول')->multiple()->preload()
                    ->relationship('user','name')->visible(auth()->user()->can('restore_any_project')),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('Open')->label('گزارش')->icon('heroicon-o-chart-pie')
                    ->url(fn ($record) => route('filament.admin.resources.projects.record',['id' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                ExportBulkAction::make()->label('دریافت خروجی'),
                Tables\Actions\BulkAction::make('bulkRelearn')
                    ->label('یادگیری زیر مجموعه ها AI')->configure()
                    ->icon('heroicon-o-arrow-path')->visible(auth()->user()->can('create_ai::words::data'))
                    ->action(function ($records) {
                        $totalWords = 0;
                        $classifier = app(\App\Services\AiKeywordClassifier::class);

                        // استخراج نوع مدل و آیدی‌ها
                        $modelType = \App\Models\Project::class; // چون این BulkAction در جدول دستورکار‌هاست
                        $modelIds  = collect($records)->pluck('id')->toArray();

                        foreach ($records as $record) {
                            $parentModel = $record;

                            if ($parentModel) {
                                $count = $classifier
                                    ->learn(
                                        $parentModel,
                                        'tasks',   // نام ریلیشن زیرمجموعه
                                        'name',               // فیلد عنوان زیرمجموعه
                                        'city',    // فیلد ثانویه مثل شهر
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

            ])->headerActions([
                Action::make('print')
                    ->label('چاپ جدول')
                    ->icon('heroicon-o-printer')
                    ->extraAttributes([
                        'onclick' => 'window.print()',
                    ]),
                FilamentExportHeaderAction::make('Export')->label('دریافت خروجی'),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TasksRelationManager::class,
            RelationManagers\LettersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
            'record' => Pages\Record::route('/{id}/record'),
        ];
    }
}
