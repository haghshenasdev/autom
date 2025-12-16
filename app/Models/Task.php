<?php

namespace App\Models;

use App\Events\TaskCreated;
use App\Models\Traits\HasStatus;
use Carbon\Carbon;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Support\RawJs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Task extends Model
{
    use HasFactory,LogsActivity,HasStatus;

    protected $fillable = [
        'name',
        'status',
        'progress',
        'description',
        'completed',
        'completed_at',
        'started_at',
        'ended_at',
        'repeat',
        'city_id',
        'amount',
        'minutes_id',
        'task_group_id',
        'created_by',
        'Responsible_id',
        'organ_id',
        'created_at',
    ];


    // ارتباط با مدل Project
    public function project()
    {
        return $this->belongsToMany(Project::class);
    }

    public function organ(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organ::class);
    }

    public function appendix_others()
    {
        return $this->morphMany(AppendixOther::class, 'appendix_other');
    }

    public function group()
    {
        return $this->belongsToMany(TaskGroup::class);
    }

    // ارتباط با مدل TaskGroup
    public function task_group()
    {
        return $this->belongsToMany(TaskGroup::class);
    }

    // ارتباط با مدل User به عنوان سازنده
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function minutes()
    {
        return $this->belongsTo(Minutes::class);
    }

    // ارتباط با مدل User به عنوان مسئول
    public function responsible()
    {
        return $this->belongsTo(User::class, 'Responsible_id');
    }

    public static function formSchema()
    {
        return [

            Forms\Components\Textarea::make('name')->label('عنوان')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')->label('توضیحات')
                ->maxLength(255),

            Forms\Components\Toggle::make('completed')->label('انجام شده'),
            Forms\Components\Toggle::make('repeat')->label('تکرار'),
            Forms\Components\DateTimePicker::make('started_at')->jalali()->label('زمان شروع')->closeOnDateSelection(),
            Forms\Components\DateTimePicker::make('ended_at')->jalali()->label('زمان پایان')->closeOnDateSelection(),
            Forms\Components\DateTimePicker::make('completed_at')->jalali()->label('تکمیل')->closeOnDateSelection(),
            Forms\Components\Select::make('Responsible_id')->label('مسئول')
                ->relationship('responsible', 'name')
                ->allowHtml()
                ->getOptionLabelFromRecordUsing(function ($record): string {
                    return view('filament.components.select-user-result')
                        ->with('name', $record->name)
                        ->with('user', $record)
                        ->with('image', $record->getFilamentAvatarUrl())
                        ->render();
                })
                ->searchable()->preload()->default(auth()->id())->visible(auth()->user()->can('restore_any_task')),
            Forms\Components\Select::make('city_id')->label('شهر')
                ->relationship('city', 'name')
                ->searchable()->preload(),
            Forms\Components\Select::make('task_group_id')->label('دسته بندی')
                ->relationship('task_group', 'name')
                ->searchable()->preload()->multiple()->createOptionForm(TaskGroup::formSchema()),
            Forms\Components\Select::make('status')
                ->options(self::getStatusListDefine())->label('وضعیت')
                ->default(null),
            Forms\Components\Select::make('organ_id')
                ->prefixActions([
                    Action::make('updateAuthor')
                        ->icon('heroicon-o-arrows-pointing-out')
                        ->label('انتخاب بر اساس نوع')
                        ->action(function (array $data,Forms\Set $set): void {
                            $set('organ_id', $data['organ_selected']);
                        })
                        ->form([
                            Select::make('organ_type_id')
                                ->label('نوع')
                                ->options(OrganType::query()->pluck('name', 'id'))
                                ->live()
                                ->searchable()
                                ->required(),
                            Forms\Components\Select::make('organ_selected')
                                ->label('ارگان')
                                ->options(fn (Get $get) => $get('organ_type_id')
                                    ? Organ::where('organ_type_id', $get('organ_type_id'))->pluck('name', 'id')
                                    : [])
                                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                                ->searchable()
                                ->preload()
                        ])
                ])
                ->label('دستگاه مربوطه')
                ->relationship('organ', 'name')
                ->getOptionLabelFromRecordUsing(fn (Model $record) => "{$record->id} - {$record->name}")
                ->searchable(['id','name'])
                ->preload(),
            Forms\Components\TextInput::make('progress')->numeric()->nullable()
                ->label('درصد انجام')->minValue(0)
                ->maxValue(100)->suffix('%'),
            TextInput::make('amount')->numeric()->nullable()->suffix('ریال')
                ->label('اعتبار اخذ شده')
                ->mask(RawJs::make('$money($input)'))
                ->stripCharacters(',')
            ,
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->created_by == null) $model->created_by = Auth::id();
        });

        static::created(function ($model) {
            event(new TaskCreated($model));
        });

        static::updating(function ( $model) {
            // اگر فیلد completed تغییر کرد
            if ($model->isDirty('completed')) {
                if ($model->completed) {
                    // اگر completed true شد، completed_at را به زمان حال تنظیم کنید
                    if ($model->completed_at == null){
                        $model->completed_at = Carbon::now();
                    }
                } else {
                    // اگر completed false شد، completed_at را null کنید
                    $model->completed_at = null;
                }
            }
        });
    }

    public static function getStatusListDefine(): array
    {
        return [
            0 => 'جدید',
            1 => 'اتمام',
            2 => 'در حال پیگیری',
            3 => 'غیرقابل پیگیری',
        ];
    }

    protected static function booted()
    {
        static::deleting(function (Task $model) {
            $model->appendix_others()->each(function ($appendix_other) {
                $appendix_other->delete();
            });
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logExcept(['updated_at','created_at'])->logAll()->logOnlyDirty() // فقط وقتی مقدار تغییر کرد ذخیره بشه
        ->dontSubmitEmptyLogs(); // لاگ خالی ثبت نشه
    }
}
