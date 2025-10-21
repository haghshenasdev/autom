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

    protected static ?string $label = "پروژه";

    protected static ?string $navigationGroup = 'پروژه / جلسه / پیگیری';


    protected static ?string $pluralModelLabel = "پروژه ها";

    protected static ?string $pluralLabel = "پروژه";


    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (!$user->can('restore_any_project')) return parent::getEloquentQuery()->where('user_id',$user->id);

        return parent::getEloquentQuery();

    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label("عنوان")
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')->label('توضیحات'),
                SelectTree::make('group_id')->label('دسته بندی')
                    ->relationship('group', 'name', 'parent_id')
                    ->enableBranchNode()->createOptionForm(auth()->user()->can('create_project::group') ? ProjectGroup::formSchema() : null),
                Forms\Components\TextInput::make('required_amount')->numeric()->nullable()
                    ->label('چشم انداز کار یا جلسه مورد نیاز')->minValue(0),
                Select::make('status')
                    ->options(Project::getStatusListDefine())->label('وضعیت')
                    ->default(null),
                Forms\Components\Select::make('user_id')->label('مسئول')
                    ->relationship('user', 'name')
                    ->searchable()->preload()->default(auth()->id())->visible(auth()->user()->can('restore_any_project')),
                Forms\Components\Select::make('city_id')->label('شهر/روستا')
                    ->relationship('city', 'name')
                    ->searchable()->preload(),
                Forms\Components\Select::make('organ')
                    ->prefixActions([
                        \Filament\Forms\Components\Actions\Action::make('updateAuthor')
                            ->icon('heroicon-o-arrows-pointing-out')
                            ->label('انتخاب بر اساس نوع')
                            ->action(function (array $data,Forms\Set $set): void {
                                $set('organ', $data['organ_selected']);
                            })
                            ->form([
                                Select::make('organ_type_id')
                                    ->label('نوع')
                                    ->options(OrganType::query()->pluck('name', 'id'))
                                    ->live()
                                    ->searchable()
                                    ->required(),
                                Forms\Components\Select::make('organ_selected')
                                    ->label('ارگان')
                                    ->options(fn (Get $get) => $get('organ_type_id')
                                        ? Organ::where('organ_type_id', $get('organ_type_id'))->pluck('name', 'id')
                                        : [])
                                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                    ->searchable()
                                    ->preload()
                            ])
                    ])
                    ->label('دستگاه مربوطه')
                    ->relationship('organ', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                    ->searchable(['id','name'])
                    ->preload(),
                TextInput::make('amount')
                    ->label('اعتبار اخذ شده')->numeric()->nullable()->suffix('ریال')
                    ->mask(RawJs::make('$money($input)'))
                    ->stripCharacters(','),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
            ])
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
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('Open')->label('گزارش گیری')->icon('heroicon-o-chart-pie')->iconButton()
                    ->url(fn ($record) => route('filament.admin.resources.projects.record',['id' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                ExportBulkAction::make()->label('دریافت فایل exel'),
            ])->headerActions([
                Action::make('print')
                    ->label('چاپ جدول')
                    ->icon('heroicon-o-printer')
                    ->extraAttributes([
                        'onclick' => 'window.print()',
                    ]),
                FilamentExportHeaderAction::make('Export'),
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
