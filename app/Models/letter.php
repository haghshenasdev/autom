<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\File;

class letter extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'file',
        'type_id',
        'status',
        'user_id',
        'titleholder_id',
        'peiroow_letter_id',
    ];

    public function letter(): BelongsTo
    {
        return $this->belongsTo(letter::class,'peiroow_letter_id');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class);
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

    public function titleholder(): BelongsTo
    {
        return $this->belongsTo(Titleholder::class);
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
        return $this->belongsToMany(Project::class, 'letter_project');
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
        static::deleted(function (letter $letter) {
            File::deleteDirectory(
                config('filesystems.disks.private.root')
                . DIRECTORY_SEPARATOR .
                $letter->id
            );
        });

        static::updating(function (letter $letter) {
            if (!is_null($letter->getOriginal('file')) && $letter->file != $letter->getOriginal('file')) {
                File::delete(
                    config('filesystems.disks.private.root')
                    . DIRECTORY_SEPARATOR .
                    self::getFilePathByArray($letter->getOriginal())
                );
            }
        });
    }

    public static function getStatusListDefine(): array
    {
        return [
            0 => 'بایگانی',
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
