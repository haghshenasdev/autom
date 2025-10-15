<?php

namespace App\Models;

use App\Models\Traits\FileEventHandler;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class Answer extends Model
{
    use HasFactory;

    protected $fillable = [
        'letter_id',
        'result',
        'summary',
        'file',
        'organ_id',
    ];

    public static function formSchema($ownerRecord): array
    {
        return [TextInput::make('result')
            ->label('نتیجه')
            ->maxLength(255)
            ,
            Textarea::make('summary')
                ->label('خلاصه')
            ,
            Select::make('organ')
                ->label('پاسخ دهنده')
                ->relationship('organ', 'name')
                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                ->prefixActions([
                    Action::make('updateAuthor')
                        ->icon('heroicon-o-arrows-pointing-out')
                        ->label('انتخاب بر اساس نوع')
                        ->action(function (array $data,Set $set): void {
                            $set('organ', $data['organ_selected']);
                        })
                        ->form([
                            Select::make('organ_type_id')
                                ->label('نوع')
                                ->options(OrganType::query()->pluck('name', 'id'))
                                ->live()
                                ->searchable()
                                ->required(),
                            Select::make('organ_selected')
                                ->label('ارگان')
                                ->options(fn (Get $get) => $get('organ_type_id')
                                    ? Organ::where('organ_type_id', $get('organ_type_id'))->pluck('name', 'id')
                                    : [])
                                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                ->searchable()
                                ->preload()
                        ])
                ])
                ->searchable()
                ->preload(),
            FileUpload::make('file')
                ->label('فایل')
                ->disk('private')
                ->downloadable()
                ->visibility('private')
                ->imageEditor()
                ->required()
                ->getUploadedFileNameForStorageUsing( fn (TemporaryUploadedFile $file) => self::getFileNamePath($file,$ownerRecord))
            ,];
    }
    public static function getFileNamePath(TemporaryUploadedFile $file,?Model $ownerRecord) : string
    {
        $letterId = $ownerRecord->id;
        $path = "{$letterId}/awrs";
        return "{$path}/awr-{$letterId}-".
            Date::now()->format('Y-m-d_H-i-s') .
            "." . explode('/',$file->getMimeType())[1];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function organ(): BelongsTo
    {
        return $this->BelongsTo(Organ::class);
    }

    // file event manager

    use FileEventHandler;

    public static string $FolderName = 'awrs';
    public static string $FilePrefix  = 'awr-';

    public static string $RelatedName = 'letter';

    public static string $disk = 'private';

    protected static function booted(): void
    {
        static::created(function (Answer $model) {
            self::renameFile($model);
        });
        static::deleted(function (Answer $model) {
            self::BootFileDeleteEvent($model);
        });

        static::updating(function (Answer $model) {
            self::BootFileUpdateEvent($model);
        });
    }
}
