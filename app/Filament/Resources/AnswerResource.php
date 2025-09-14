<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnswerResource\Pages;
use App\Filament\Resources\AnswerResource\RelationManagers;
use App\Models\Answer;
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

class AnswerResource extends Resource
{
    protected static ?string $model = Answer::class;

    protected static ?string $navigationGroup = 'نامه';

    protected static ?int $navigationSort = 1;


    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';

    protected static ?string $label = "جواب نامه";


    protected static ?string $pluralModelLabel = "جواب ها";

    protected static ?string $pluralLabel = "جواب نامه";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('letter_id')
                    ->label('نامه')
                    ->relationship('letter', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->subject}")
                    ->searchable()
                    ->preload()->required(),
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
                    ->getUploadedFileNameForStorageUsing( fn (TemporaryUploadedFile $file,?Model $record) => self::getFileNamePath($file, $record))
                ,
            ]);
    }

    private static function getFileNamePath(TemporaryUploadedFile $file,?Model $record) : string
    {
        $letterId = $record->letter_id;
        $path = "{$letterId}/awrs";
//        $FPath= config('filesystems.disks.private.root'). $path;
//        File::ensureDirectoryExists($FPath);
//        (($record == null) ? count(scandir($FPath)) -2 : $record->id )
        return "{$path}/awr-{$letterId}-".
            Date::now()->format('Y-m-d_H-i-s') .
            "." . explode('/',$file->getMimeType())[1];

    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('result')
            ->columns([
                Tables\Columns\TextColumn::make('result'),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnswers::route('/'),
            'create' => Pages\CreateAnswer::route('/create'),
            'edit' => Pages\EditAnswer::route('/{record}/edit'),
        ];
    }
}
