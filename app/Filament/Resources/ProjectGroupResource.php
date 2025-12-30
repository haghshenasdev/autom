<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectGroupResource\Pages;
use App\Filament\Resources\ProjectGroupResource\RelationManagers;
use App\Models\ProjectGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectGroupResource extends Resource
{
    protected static ?string $model = ProjectGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static ?string $label = "دسته بندی دستورکار";

    protected static ?string $navigationGroup = 'دستورکار / فعالیت ها';


    protected static ?string $pluralModelLabel = "دسته بندی دستورکار";

    protected static ?string $pluralLabel = "دسته بندی دستورکار";

    public static function form(Form $form): Form
    {
        return $form
            ->schema(ProjectGroup::formSchema());
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
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProjectsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectGroups::route('/'),
            'create' => Pages\CreateProjectGroup::route('/create'),
            'edit' => Pages\EditProjectGroup::route('/{record}/edit'),
        ];
    }
}
