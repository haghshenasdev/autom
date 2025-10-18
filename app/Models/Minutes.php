<?php

namespace App\Models;

use App\Models\Traits\FileEventHandler;
use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Minutes extends Model
{
    use HasFactory,LogsActivity;

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
//            Select::make('organ')
//                ->label('امضا کنندگان')
//                ->required()
//                ->relationship('organ', 'name')->multiple()
//                ->searchable()
//                ->preload()->createOptionForm(Organ::formSchema()),

            Select::make('organ')
                ->prefixActions([
                    Action::make('updateAuthor')
                        ->icon('heroicon-o-arrows-pointing-out')
                        ->label('انتخاب بر اساس نوع')
                        ->action(function (array $data,Set $set,Get $get): void {
                            $organ_owners = $get('organ');
                            $set('organ', array_merge($organ_owners,$data['organ_selected']));
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
                                ->multiple()
                                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                ->searchable()
                                ->preload()
                        ])
                ])
                ->relationship('organ','name')
                ->multiple()
                ->searchable(['organs.name','organs.id'])
                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                ->label('امضا کنندگان'),
            Select::make('typer_id')->label('نویسنده')
                ->visible(auth()->user()->can('restore_any_minutes'))
                ->default(Auth::id())
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
                ->hintAction(
                    Action::make('باز کردن لینک')
                        ->label('نمایش فایل')
                        ->url(fn($record) => $record ? env('APP_URL').'/appendix-other-show/'.$record->getFilePath() : null, shouldOpenInNewTab: true)
                        ->color('primary')
                        ->icon('heroicon-o-arrow-top-right-on-square'),
                )
                ->getUploadedFileNameForStorageUsing( fn (TemporaryUploadedFile $file,?Model $record) => self::getFileNamePath($file,$record)),
            SelectTree::make('group_id')->label('دسته بندی')
                ->relationship('group', 'name', 'parent_id')
                ->enableBranchNode()->createOptionForm(MinutesGroup::formSchema()),
            DateTimePicker::make('date')->default(Date::now())->jalali()->label('تاریخ')->required()->closeOnDateSelection(),
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
            if (!is_null($model->getOriginal('file')) && $model->file != $model->getOriginal('file')) {
                File::delete(
                    self::getRootPath() .
                    self::getFilePathByArray($model->id,$model->getOriginal(),$model->appendix_other_type ?? null)
                );
                if (str_contains($model->file,'.')) self::renameFile($model);
            }
        });
        static::deleting(function (Minutes $model) {
            $model->appendix_others()->each(function ($appendix_other) {
                $appendix_other->delete();
            });
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults();
    }

}
