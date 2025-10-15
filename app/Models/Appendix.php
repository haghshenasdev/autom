<?php

namespace App\Models;

use App\Models\Traits\FileEventHandler;
use Barryvdh\Debugbar\Facades\Debugbar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class Appendix extends Model
{
    use HasFactory;

    protected $fillable = [
        'file',
        'title',
    ];

    protected $table = 'appendices';

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    protected static function getPatternFilePath(){

    }

    // file event manager

    use FileEventHandler;

    public static string $FolderName = 'apds';
    public static string $FilePrefix  = 'apd-';

    public static string $RelatedName = 'letter';

    public static string $disk = 'private';

    protected static function booted(): void
    {
        static::created(function (Appendix $model) {
            self::renameFile($model);
        });
        static::deleted(function (Appendix $model) {
            self::BootFileDeleteEvent($model);
        });

        static::updating(function (Appendix $model) {
            self::BootFileUpdateEvent($model);
        });
    }
}
