<?php

namespace App\Models;

use App\Models\Traits\FileEventHandler;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class Minutes extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'date',
        'text',
        'file',
        'typer_id',
        'task_id',
    ];

    public function typer(){
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsToMany(MinutesGroup::class);
    }

    public function organ()
    {
        return $this->belongsToMany(Organ::class,'minute_organ','minute_id','organ_id');
    }

    public function tasks(){
        return $this->hasMany(Task::class);
    }

    public function task_creator(){
        return $this->belongsTo(Task::class,'task_id');
    }

    public function approves(){
        return $this->hasMany(Approve::class,'minute_id');
    }

    public static function formSchema()
    {
        return [
            TextInput::make('title')
                ->label('عنوان')->required()
                ->maxLength(255),
            Textarea::make('text')
                ->label('متن')
            ,
            Select::make('organ')
                ->label('امضا کنندگان')
                ->required()
                ->relationship('organ', 'name')->multiple()
                ->searchable()
                ->preload()->createOptionForm(Organ::formSchema()),
            Select::make('typer_id')->label('نویسنده')
                ->relationship('typer', 'name')
                ->searchable()->preload(),
            Select::make('task_id')->label('نوشته شده در')->required()
                ->relationship('task_creator', 'name')
                ->searchable()->preload(),
            FileUpload::make('file')
                ->label('فایل')
                ->disk('private_appendix_other')
                ->downloadable()
                ->visibility('private')
                ->imageEditor()
                ->required()
                ->getUploadedFileNameForStorageUsing( fn (TemporaryUploadedFile $file,?Model $record) => self::getFileNamePath($file,$record)),
            SelectTree::make('group_id')->label('دسته بندی')
                ->relationship('group', 'name', 'parent_id')
                ->enableBranchNode()->createOptionForm(MinutesGroup::formSchema()),
            DateTimePicker::make('date')->default(Date::now())->jalali()->label('تاریخ')->required(),
        ];
    }

    private static function getFileNamePath(TemporaryUploadedFile $file,?Model $record) : string
    {
        $path = "minutes_temp";
        return "{$path}/".
            Date::now()->format('Y-m-d_H-i-s') .
            "." . explode('/',$file->getMimeType())[1];

    }

    public function appendix_others()
    {
        return $this->morphMany(AppendixOther::class, 'appendix_other');
    }

    // file event manager

    use FileEventHandler;

    public static ?string $FolderName = null;
    public static string $FilePrefix  = '';

    public static string $RelatedName = 'appendix_others';

    public static string $disk = 'private_appendix_other';

    public function getFilePath() : string|null
    {
        return (is_null($this->file)) ? null : 'minutes'
            . DIRECTORY_SEPARATOR
            . $this->id
            . DIRECTORY_SEPARATOR
            . self::$FilePrefix . $this->id . '.' . $this->file;
    }


    protected static function getPathPattern($modelId,$file,$letterId,$type = null): ?string
    {
        if (!is_null($file)) {

                return
                    'minutes'
                    . DIRECTORY_SEPARATOR
                    . $modelId
                    . DIRECTORY_SEPARATOR
                    . self::$FilePrefix . $modelId . '.' . $file;


        }

        return null;
    }

    protected static function booted(): void
    {
        static::created(function (Minutes $model) {
            self::renameFile($model);
        });
        static::deleted(function (Minutes $model) {
            self::BootFileDeleteEvent($model);
        });

        static::updating(function (Minutes $model) {
            self::BootFileUpdateEvent($model);
        });

        static::deleting(function (Minutes $model) {
            $model->appendix_others()->each(function ($appendix_other) {
                $appendix_other->delete();
            });
        });
    }


}
