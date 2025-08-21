<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers;
use App\Models\Project;
use App\Models\ProjectGroup;
use App\Models\Referral;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use IbrahimBougaoua\FilaProgress\Tables\Columns\ProgressBar;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $label = "پروژه";

    protected static ?string $navigationGroup = 'پروژه / جلسه / پیگیری';


    protected static ?string $pluralModelLabel = "پروژه ها";

    protected static ?string $pluralLabel = "پروژه";


    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->label("عنوان")
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')->label('توضیحات')
                    ->maxLength(255),
                SelectTree::make('group_id')->label('دسته بندی')
                    ->relationship('group', 'name', 'parent_id')
                    ->enableBranchNode()->createOptionForm(ProjectGroup::formSchema())
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label("عنوان")
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')->label("توضیحات")
                    ->searchable(),
                Tables\Columns\TextColumn::make('user_id')->label("ایجاد کننده")
                    ->state(function (Model $record): string {
                        return $record->user()->first('name')->name;
                    }),
                Tables\Columns\TextColumn::make('created_at')->label("ایجاد")
                    ->jalaliDateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label("تغییر")
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ProgressBar::make('پیشرفت')
                    ->getStateUsing(function ($record) {
                        $total = $record->tasks()->count();
                        $progress = $record->tasks()->where('completed',true)->count();
                        return [
                            'total' => $total,
                            'progress' => $progress,
                        ];
                    }),
            ])
            ->filters([
                Filter::make('tree')
                    ->form([
                        SelectTree::make('group')
                            ->relationship('group', 'name', 'parent_id')
                            ->independent(false)
                            ->enableBranchNode(),
                    ])
                    ->query(function (Builder $query, array $data) {

                        return $query->when($data['group'], function ($query, $categories) {
                            return $query->whereHas('group', fn($query) => $query->whereIn('id', [$categories]));
                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['group']) {
                            return null;
                        }
                        return __('Categories') . ': ' . implode(', ', ProjectGroup::query()->whereIn('id', [$data['group']])->get()->pluck('name')->toArray());
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('Open')->label('گزارش گیری')
                    ->url(fn ($record) => route('filament.admin.resources.projects.record',['id' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TasksRelationManager::class,
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
