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
        return $this->belongsToMany(User::class,'cartables');
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
        return LogOptions::defaults();
    }
}
