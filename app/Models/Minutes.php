<?php

namespace App\Models;

use CodeWithDennis\FilamentSelectTree\SelectTree;
use Filament\Forms\Components\DatePicker;
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
    ];

    public function typer(){
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsToMany(MinutesGroup::class);
    }

    public function titleholder()
    {
        return $this->belongsToMany(Titleholder::class);
    }

    public function tasks(){
        return $this->hasMany(Task::class);
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
            Select::make('titleholder')
                ->label('امضا کنندگان')
                ->required()
                ->relationship('titleholder', 'name')->multiple()
                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->name} - {$record->official} ، {$record->organ()->first('name')->name}")
                ->searchable()
                ->preload()->createOptionForm(Titleholder::formSchema()),
            Select::make('typer_id')->label('نویسنده')
                ->relationship('typer', 'name')
                ->searchable()->preload(),
            FileUpload::make('file')
                ->label('فایل')
                ->disk('private2')
                ->downloadable()
                ->visibility('private')
                ->imageEditor()
                ->required(),
            SelectTree::make('group_id')->label('دسته بندی')
                ->relationship('group', 'name', 'parent_id')
                ->enableBranchNode()->createOptionForm(MinutesGroup::formSchema()),
            DatePicker::make('date')->default(Date::now())->jalali()->label('تاریخ')->required(),
        ];
    }




}
