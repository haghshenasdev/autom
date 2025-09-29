<?php

namespace App\Filament\Resources\MinutesResource\RelationManagers;

use App\Models\Approve;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ApprovesRelationManager extends RelationManager
{
    protected static string $relationship = 'approves';

    protected static ?string $label = 'مصوبه ها';

    protected static ?string $pluralLabel = 'مصوبه';

    protected static ?string $modelLabel = 'مصوبه';

    protected static ?string $title = 'مصوبه ها';

    public function form(Form $form): Form
    {
        return $form
            ->schema(Approve::formSchema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('id')->label('ثبت')
                    ->searchable()->sortable(),
                TextColumn::make('title')->label('عنوان')
                    ->searchable(),
                TextColumn::make('project.name')->label('پروژه')->listWithLineBreaks()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('organ.name')->label('اداره')->listWithLineBreaks()->toggleable(),
                TextColumn::make('amount')->label('اعتبار')->toggleable()->sortable()->numeric()->suffix('ریال'),
                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => Approve::getStatusColor($state))
                    ->state(function (Model $record): string {
                        return Approve::getStatusLabel($record->status);})->sortable()->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
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
