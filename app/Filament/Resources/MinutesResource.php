<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MinutesResource\Pages;
use App\Filament\Resources\MinutesResource\RelationManagers;
use App\Models\Minutes;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use IbrahimBougaoua\FilaProgress\Tables\Columns\ProgressBar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MinutesResource extends Resource
{
    protected static ?string $model = Minutes::class;

    protected static ?string $label = "صورت جلسه";

    protected static ?string $navigationGroup = 'صورت جلسه';


    protected static ?string $pluralModelLabel = "صورت جلسه ها";

    protected static ?string $pluralLabel = "صورت جلسه";

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema(Minutes::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('عنوان')
                    ->searchable(),
                TextColumn::make('titleholder')->label('امضا کنندگان')
                    ->html()->alignCenter()
                    ->state(function (Model $record): string {
                        $customers = $record->titleholder()->get();
                        $string = '';
                        foreach ($customers as $recordi){
                            $string .= $recordi->name ."-". $recordi->official . " ، ".$recordi->organ()->first('name')->name . "<br>";
                        }
                        return $string;
                    }),
                TextColumn::make('date')->label(' تاریخ')->jalaliDateTime(),
                ProgressBar::make('درصد تحقق')
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
            RelationManagers\TasksRelationManager::class,
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
