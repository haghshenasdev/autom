<?php

namespace App\Filament\Resources\LetterResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
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
            ->schema([
                Forms\Components\TextInput::make('result')
                    ->label('نتیجه')
                    ->maxLength(255)
                ,
                Forms\Components\Textarea::make('summary')
                    ->label('خلاصه')
                ,
                Forms\Components\Select::make('titleholder')
                    ->label('پاسخ دهنده')
                    ->relationship('titleholder', 'name')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->name} - {$record->official} ، {$record->organ()->first('name')->name}")
                    ->searchable()
                    ->preload(),
                FileUpload::make('file')
                    ->label('فایل')
                    ->disk('private')
                    ->downloadable()
                    ->visibility('private')
                    ->imageEditor()
                    ->required()
                    ->getUploadedFileNameForStorageUsing( fn (TemporaryUploadedFile $file,?Model $record) => $this->getFileNamePath($file,$record))
                ,
            ]);
    }

    private function getFileNamePath(TemporaryUploadedFile $file,?Model $record) : string
    {
        $letterId = $this->ownerRecord->id;
        $path = "{$letterId}/awrs";
//        $FPath= config('filesystems.disks.private.root'). $path;
//        File::ensureDirectoryExists($FPath);
//        (($record == null) ? count(scandir($FPath)) -2 : $record->id )
        return "{$path}/awr-{$letterId}-".
            Date::now()->format('Y-m-d_H-i-s') .
            "." . explode('/',$file->getMimeType())[1];

    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('result')
            ->columns([
                Tables\Columns\TextColumn::make('result')->label('نتیجه'),
                Tables\Columns\TextColumn::make('summary')->label('خلاصه'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->mutateRecordDataUsing(function (array $data){
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
