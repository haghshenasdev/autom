<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganResource\Pages;
use App\Filament\Resources\OrganResource\RelationManagers;
use App\Models\Organ;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrganResource extends Resource
{
    protected static ?string $model = Organ::class;

    protected static ?string $navigationGroup = 'مراجع دریافت نامه';


    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $label = "سازمان";


    protected static ?string $pluralModelLabel = "سازمان ها";

    protected static ?string $pluralLabel = "سازمان";

    public static function form(Form $form): Form
    {
        return $form
            ->schema(Organ::formSchema());
    }

    public static function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('id')->searchable()->sortable(),
            TextColumn::make('name')->label('عنوان')->searchable(),
            TextColumn::make('type.name')->label('نوع'),
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
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListOrgans::route('/'),
            'create' => Pages\CreateOrgan::route('/create'),
            'edit' => Pages\EditOrgan::route('/{record}/edit'),
        ];
    }
}
