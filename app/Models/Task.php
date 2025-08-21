<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Auth;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'description',
        'completed',
        'completed_at',
        'started_at',
        'ended_at',
        'repeat',
        'task_group_id',
        'created_by',
        'Responsible_id'
    ];


    // ارتباط با مدل Project
    public function project()
    {
        return $this->belongsToMany(Project::class);
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

    // ارتباط با مدل User به عنوان مسئول
    public function responsible()
    {
        return $this->belongsTo(User::class, 'Responsible_id');
    }

    public static function formSchema()
    {
        return [

            Forms\Components\TextInput::make('name')->label('عنوان')
                ->required()
                ->maxLength(255),
            Forms\Components\Textarea::make('description')->label('توضیحات')
                ->maxLength(255),

            Forms\Components\Toggle::make('completed')->label('انجام شده'),
            Forms\Components\Toggle::make('repeat')->label('تکرار'),
            Forms\Components\DatePicker::make('started_at')->jalali()->label('زمان شروع'),
            Forms\Components\DatePicker::make('ended_at')->jalali()->label('زمان پایان'),
            Forms\Components\DatePicker::make('completed_at')->jalali()->label('تکمیل'),
            Forms\Components\Select::make('Responsible_id')->label('مسئول')
                ->relationship('responsible', 'name')
                ->searchable()->preload(),
            Forms\Components\Select::make('task_group_id')->label('دسته بندی')
                ->relationship('task_group', 'name')
                ->searchable()->preload()->multiple()->createOptionForm(TaskGroup::formSchema()),
            Forms\Components\Select::make('status')
                ->options(self::getStatusListDefine())->label('وضعیت')
                ->default(null)
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_by = Auth::id();
        });

        static::updating(function ( $model) {
            // اگر فیلد completed تغییر کرد
            if ($model->isDirty('completed')) {
                if ($model->completed) {
                    // اگر completed true شد، completed_at را به زمان حال تنظیم کنید
                    $model->completed_at = Carbon::now();
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

    public static function getStatusLabel(int|null $i): int|string
    {
        $data = self::getStatusListDefine();

        if (array_key_exists($i,$data)){
            return $data[$i];
        }elseif (is_null($i)){
            return 'بدون وضعیت';
        }

        return $i;
    }
}
