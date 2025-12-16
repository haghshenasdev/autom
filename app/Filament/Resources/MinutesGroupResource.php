<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MinutesGroupResource\Pages;
use App\Filament\Resources\MinutesGroupResource\RelationManagers;
use App\Models\MinutesGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MinutesGroupResource extends Resource
{
    protected static ?string $model = MinutesGroup::class;

    protected static ?string $label = "دسته بندی صورت جلسه";

    protected static ?string $navigationGroup = 'صورت جلسه';


    protected static ?string $pluralModelLabel = "دسته بندی صورت جلسه";

    protected static ?string $pluralLabel = "دسته بندی صورت جلسه";

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(MinutesGroup::formSchema());
    }

    public static function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('name')->label('نام')
                ->searchable(),
            TextColumn::make('parent.name')->label('گروه اصلی')
                ->numeric()
                ->sortable(),
            TextColumn::make('created_at')->label('تاریخ ایجاد')
                ->dateTime()->jalaliDateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')->label('تاریخ ویرایش')
                ->dateTime()->jalaliDateTime()
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
            ])
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMinutesGroups::route('/'),
            'create' => Pages\CreateMinutesGroup::route('/create'),
            'edit' => Pages\EditMinutesGroup::route('/{record}/edit'),
        ];
    }
}
