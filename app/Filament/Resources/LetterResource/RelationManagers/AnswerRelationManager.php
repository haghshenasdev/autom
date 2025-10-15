<?php

namespace App\Filament\Resources\LetterResource\RelationManagers;

use App\Models\Answer;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Date;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AnswerRelationManager extends RelationManager
{
    protected static string $relationship = 'Answer';

    protected static ?string $label = 'جواب';

    protected static ?string $pluralLabel = 'جواب';

    protected static ?string $modelLabel = 'جواب';

    protected static ?string $title = 'جواب ها';

    public function form(Form $form): Form
    {
        return $form
            ->schema(Answer::formSchema($this->ownerRecord));
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('result')
            ->columns([
                Tables\Columns\TextColumn::make('result')->label('نتیجه'),
                Tables\Columns\TextColumn::make('summary')->label('خلاصه'),
                Tables\Columns\TextColumn::make('organ.name')->label('پاسخ دهنده'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Action::make('باز کردن لینک')
                    ->label('نمایش فایل')
                    ->url(fn($record) => env('APP_URL').'/private-show/'.$record->getFilePath($this->ownerRecord->id), shouldOpenInNewTab: true)
                    ->color('primary')
                    ->icon('heroicon-o-arrow-top-right-on-square'),
                EditAction::make()->mutateRecordDataUsing(function (array $data){
                    $data['file'] = $this->cachedMountedTableActionRecord->getFilePath($this->ownerRecord->id);
                    return $data;
                })->mutateFormDataUsing(function (array $data){
                    if ($data['file'] == $this->cachedMountedTableActionRecord->getFilePath($this->ownerRecord->id))
                    {
                        $data['file'] = explode('.',$data['file'])[1];
                    }
                    return $data;
                }),
                Tables\Actions\DeleteAction::make(),
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
}
