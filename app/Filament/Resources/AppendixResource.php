<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AppendixResource\Pages;
use App\Filament\Resources\AppendixResource\RelationManagers;
use App\Models\Appendix;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Date;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AppendixResource extends Resource
{
    protected static ?string $model = Appendix::class;

    protected static ?string $navigationGroup = 'دبیرخانه';

    protected static ?int $navigationSort = 4;


    protected static ?string $navigationIcon = 'heroicon-o-folder-plus';

    protected static ?string $label = "ضمیمه";


    protected static ?string $pluralModelLabel = "ضمیمه ها";

    protected static ?string $pluralLabel = "ضمیمه";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->label('عنوان')
                    ->maxLength(255)
                ,
                Forms\Components\Select::make('letter_id')
                    ->label('نامه')
                    ->relationship('letter', 'subject')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->subject}")->lazy()
                    ->searchable()
                    ->preload()
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
                    ->getUploadedFileNameForStorageUsing( fn (TemporaryUploadedFile $file,?Model $record) => self::getFileNamePath($file, $record))
                ,
            ]);
    }


    private static function getFileNamePath(TemporaryUploadedFile $file,?Model $record) : string
    {
        $letterId = $record->letter_id;
        $path = "{$letterId}/apds";
//        $FPath= config('filesystems.disks.private.root'). $path;
//        File::ensureDirectoryExists($FPath);
//        (($record == null) ? count(scandir($FPath)) -2 : $record->id )
        return "{$path}/apd-{$letterId}-".
            Date::now()->format('Y-m-d_H-i-s') .
            "." . explode('/',$file->getMimeType())[1];

    }

    public static function table(Table $table): Table
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
                Tables\Actions\EditAction::make(),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppendixes::route('/'),
            'create' => Pages\CreateAppendix::route('/create'),
            'edit' => Pages\EditAppendix::route('/{record}/edit'),
        ];
    }
}
