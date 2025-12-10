<?php

namespace App\Models;

use App\Models\Traits\HasStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\File;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Letter extends Model
{
    use HasFactory,HasStatus,LogsActivity;

    protected $fillable = [
        'subject',
        'description',
        'file',
        'type_id',
        'status',
        'kind',
        'user_id',
        'titleholder_id',
        'peiroow_letter_id',
        'summary',
        'mokatebe',
        'daftar_id',
        'organ_id',
        'created_at',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class,'peiroow_letter_id');
    }


    public static function getKindListDefine(): array
    {
        return [
            0 => 'وارده',
            1 => 'صادره',
        ];
    }

    public function customers()
    {
        return $this->morphedByMany(Customer::class, 'owner','owner_letter');
    }

    public function organs_owner()
    {
        return $this->morphedByMany(Organ::class, 'owner','owner_letter');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class,'cartables')->using(Cartable::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Type::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organ(): BelongsTo
    {
        return $this->belongsTo(Organ::class);
    }

    public function daftar(): BelongsTo
    {
        return $this->belongsTo(Organ::class,'daftar_id')->where('organ_type_id',20);
    }

    public function Answer(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function timeline()
    {
        // نگاشت نام فیلدها به فارسی
        $fieldLabels = [
            'subject' => 'موضوع',
            'status' => 'وضعیت',
            'description'=> 'توضیحات',
            'file' => 'فایل',
            'type_id'=> 'نوع',
            'kind'=> 'صادره یا وارده',
            'peiroow_letter_id'=> 'پیرو نامه',
            'summary'=> 'خلاصه/نتیجه',
            'mokatebe' => 'شماره مکاتبه',
            'daftar_id' => 'آیدی دفتر',
            'organ_id' => 'گیرنده نامه',

            'rule' => 'دستور',
            'result' => 'نتیجه',
            'by_user_id' => 'ارجاع کننده',
            'to_user_id' => 'ارجاع شده',
            'checked' => 'وضعیت بررسی',
            'letter_id' => 'نامه',
        ];

        // نگاشت رویدادهای Activity به فارسی
        $eventLabels = [
            'created' => 'ایجاد',
            'updated' => 'بروزرسانی',
            'deleted' => 'حذف',
        ];

        $events = collect();

        // اضافه کردن خود نامه
        $events->push([
            'type' => 'letter',
            'title' => 'ایجاد نامه',
            'description' => isset($this->user->name) ? 'نامه توسط ' . $this->user->name  . ' ثبت شد .' : 'نامه ثبت شد .',
            'created_at' => $this->created_at,
            'icon' => 'heroicon-o-document-text',
            'color' => 'gray',
        ]);

        // اضافه کردن پاسخ‌ها
        foreach ($this->Answer as $answer) {
            $events->push([
                'type' => 'answer',
                'title' => 'پاسخ ثبت شد',
                'description' => $answer->result ? 'نتیجه : ' . $answer->result . ($answer->summary ?   " -" . ' خلاصه : ' . $answer->summary : '') : '',
                'created_at' => $answer->created_at,
                'icon' => 'heroicon-o-chat-bubble-left-right',
                'color' => 'success',
            ]);
        }

        // اضافه کردن ارجاعات
        foreach ($this->referrals as $referral) {
            $events->push([
                'type' => 'referral',
                'title' => 'ارجاع شد',
                'description' => 'توسط ' . $referral->by_users->name . ' به ' . $referral->users->name . ' ارجاع شد ',
                'created_at' => $referral->created_at,
                'icon' => 'heroicon-o-arrow-path',
                'color' => 'warning',
            ]);

            // لاگ‌های فعالیت ارجاع
            foreach ($referral->activities as $activity) {
                $changes = [];

                if ($activity->event === 'updated') {
                    $old = $activity->properties['old'] ?? [];
                    $new = $activity->properties['attributes'] ?? [];

                    foreach ($new as $field => $value) {
                        $changes[$field] = [
                            'label' => $fieldLabels[$field] ?? $field,
                            'old' => self::castFieldValue($field, $old[$field] ?? null),
                            'new' => self::castFieldValue($field, $value),
                        ];
                    }
                }

                $events->push([
                    'type' => 'referral_activity',
                    'title' => 'تغییر وضعیت ارجاع' . ' به ' . ($referral->users->name ?? '---'),
                    'description' => $activity->description,
                    'created_at' => $activity->created_at,
                    'icon' => 'heroicon-o-adjustments-horizontal',
                    'color' => 'warning',
                    'event' => $activity->event,
                    'changes' => $changes,
                    'user' => $activity->causer?->name ?? 'سیستم',
                ]);
            }
        }

        // اضافه کردن لاگ‌های فعالیت
        // فعالیت‌ها
        foreach ($this->activities as $activity) {
            $changes = [];

            if ($activity->event === 'updated') {
                $old = $activity->properties['old'] ?? [];
                $new = $activity->properties['attributes'] ?? [];

                foreach ($new as $field => $value) {
                    $changes[$field] = [
                        'label' => $fieldLabels[$field] ?? $field,
                        'old' => self::castFieldValue($field, $old[$field] ?? null),
                        'new' => self::castFieldValue($field, $value),
                    ];
                }
            }

            $events->push([
                'type' => 'activity',
                'title' => $eventLabels[$activity->event] ?? $activity->event,
                'description' => $activity->description,
                'created_at' => $activity->created_at,
                'icon' => 'heroicon-o-clock',
                'color' => 'info',
                'event' => $activity->event,
                'changes' => $changes,
                'user' => $activity->causer?->name ?? 'سیستم', // نام شخص تغییر دهنده
            ]);
        }

        // مرتب‌سازی بر اساس زمان
        return $events->sortBy('created_at');
    }

    protected static function castFieldValue(string $field, $value)
    {
        if ($value === null) {
            return null;
        }

        switch ($field) {
            case 'kind':
                return self::getKindLabel($value);
            case 'status':
                return self::getStatusLabel($value);
            case 'checked':
                return $value == 1 ? 'بررسی شده' : 'بررسی نشده';
            case 'to_user_id':
            case 'by_user_id':
                return User::query()->find($value)->name ?? $value;
            case 'organ_id':
                return Organ::query()->find($value)->name ?? $value;
            default:
                return $value;
        }
    }



    public function replications(): HasMany
    {
        return $this->hasMany(Replication::class);
    }
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'letter_project')->withPivot('summary');
    }

    public function Appendix(): HasMany
    {
        return $this->hasMany(Appendix::class);
    }

    public function getFilePath() : string|null
    {
        return (is_null($this->file)) ? null : $this->id.'/'.$this->id.'.'.$this->file;
    }

    public static function getFilePathByArray(Array $record) : string|null
    {
        return (is_null($record['file'])) ? null : $record['id'].'/'.$record['id'].'.'.$record['file'];
    }

    protected static function booted(): void
    {
        static::deleted(function (Letter $letter) {
            File::deleteDirectory(
                config('filesystems.disks.private.root')
                . DIRECTORY_SEPARATOR .
                $letter->id
            );
        });

        static::updating(function (Letter $letter) {
            if (!is_null($letter->getOriginal('file')) && $letter->file != $letter->getOriginal('file')) {
                File::delete(
                    config('filesystems.disks.private.root')
                    . DIRECTORY_SEPARATOR .
                    self::getFilePathByArray($letter->getOriginal())
                );
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logExcept(['updated_at','created_at'])->logAll() // فیلدهایی که می‌خوای تغییراتشون ثبت بشه
        ->logOnlyDirty() // فقط وقتی مقدار تغییر کرد ذخیره بشه
        ->dontSubmitEmptyLogs(); // لاگ خالی ثبت نشه;
    }
}
