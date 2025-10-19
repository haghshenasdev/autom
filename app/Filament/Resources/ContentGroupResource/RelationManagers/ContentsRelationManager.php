<?php

namespace App\Filament\Resources\ContentGroupResource\RelationManagers;

use App\Models\Content;
use Filament\Forms;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ContentsRelationManager extends RelationManager
{
    protected static string $relationship = 'contents';

    protected static ?string $label = "محتوا";
    protected static ?string $title = "محتوا";

    protected static ?string $modelLabel = 'محتوا';

    protected static ?string $pluralModelLabel = "محتوا";

    protected static ?string $pluralLabel = "محتوا";


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('عنوان'),
                RichEditor::make('body')
                    ->label('محتوا')
                    ->fileAttachmentsDisk('private2')
                    ->fileAttachmentsVisibility('private')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->limit(20)
                    ->state(function (Content $record) {
                        if ($record->title === null) {
                            return strip_tags(html_entity_decode(trim($record->body)));
                        }
                        return $record->title;
                    })
                    ->label('عنوان'),

                Tables\Columns\TextColumn::make('group.name')->label('دسته بندی'),
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
