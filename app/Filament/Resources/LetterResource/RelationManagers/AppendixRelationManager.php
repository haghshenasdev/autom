<?php

namespace App\Filament\Resources\LetterResource\RelationManagers;

use Barryvdh\Debugbar\Facades\Debugbar;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AppendixRelationManager extends RelationManager
{
    protected static string $relationship = 'Appendix';

    protected static ?string $label = 'ضمیمه ها';

    protected static ?string $pluralLabel = 'ضمیمه';

    protected static ?string $modelLabel = 'ضمیمه';

    protected static ?string $title = 'ضمیمه ها';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->label('عنوان')
                    ->maxLength(255)
                ,
                FileUpload::make('file')
                    ->label('فایل')
                    ->disk('private')
                    ->downloadable()
                    ->visibility('private')
                    ->imageEditor()
                    ->required()
                    //->hiddenOn('aaaa')
//                    ->getUploadedFileUsing(fn (?Model $record) => $record->getFilePath($this->ownerRecord->id))
                    ->getUploadedFileNameForStorageUsing( fn (TemporaryUploadedFile $file,?Model $record) => $this->getFileNamePath($file,$record))
                ,
            ]);
    }


    private function getFileNamePath(TemporaryUploadedFile $file,?Model $record) : string
    {
            $letterId = $this->ownerRecord->id;
            $path = "{$letterId}/apds";
//        $FPath= config('filesystems.disks.private.root'). $path;
//        File::ensureDirectoryExists($FPath);
//        (($record == null) ? count(scandir($FPath)) -2 : $record->id )
            return "{$path}/apd-{$letterId}-".
                Date::now()->format('Y-m-d_H-i-s') .
                "." . explode('/',$file->getMimeType())[1];

    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان'),
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
