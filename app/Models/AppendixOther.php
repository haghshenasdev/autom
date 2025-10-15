<?php

namespace App\Models;

use App\Models\Traits\FileEventHandler;
use App\Models\Traits\HandlesPrivateFileLifecycle;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class AppendixOther extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'description',
        'file',
        'appendix_other_type',
        'appendix_other_id',
    ];

    public function appendix_other(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public static function formSchema() : array
    {
        return [
            TextInput::make('title')->label('عنوان')
                ->maxLength(255),
            TextInput::make('description')->label('توضیحات')
                ->maxLength(255),
        ];
    }

    // file event manager

    use FileEventHandler;

    public static ?string $FolderName = null;
    public static string $FilePrefix  = '';

    public static string $RelatedName = 'appendix_other';

    public static string $disk = 'private_appendix_other';

    public function getFilePath() : string|null
    {
        return (is_null($this->file)) ? null : strtolower(class_basename($this->appendix_other_type))
            . DIRECTORY_SEPARATOR
            . $this->appendix_other_id
            . DIRECTORY_SEPARATOR
            . self::$FilePrefix . $this->appendix_other_id . '-' . $this->id . '.' . $this->file;
    }

    protected static function booted(): void
    {
        static::created(function (AppendixOther $model) {
            self::renameFile($model);
        });
        static::deleted(function (AppendixOther $model) {
            self::BootFileDeleteEvent($model);
        });

        static::updating(function (AppendixOther $model) {
            self::BootFileUpdateEvent($model);
        });
    }
}
