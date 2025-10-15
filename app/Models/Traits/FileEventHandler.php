<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

trait FileEventHandler
{
//    public static string $FolderName;
//    public static string $FilePrefix;
//   public static string $RelatedName = 'letter';
//   public static string $disk = 'private';

    public static function getRootPath(): string
    {
        return config('filesystems.disks.'.self::$disk.'.root'). DIRECTORY_SEPARATOR;
    }
    protected static function getPathPattern($modelId,$file,$letterId,$type = null): ?string
    {
        if (!is_null($file)) {
            if ($type === null){
                return $letterId
                    . DIRECTORY_SEPARATOR
                    . self::$FolderName
                    . DIRECTORY_SEPARATOR
                    . self::$FilePrefix . $letterId . '-' . $modelId . '.' . $file;
            }else{
                return
                    strtolower(class_basename($type))
                    . DIRECTORY_SEPARATOR
                    . $letterId
                    . DIRECTORY_SEPARATOR
                    . self::$FilePrefix . $letterId . '-' . $modelId . '.' . $file;
            }

        }

        return null;
    }

    public function getFilePath(int $letterId) : string|null
    {
        return self::getPathPattern($this->id,$this->file,$letterId);
    }

    public static function getFilePathByArray(?int $letterId,Array $record,$type) : string|null
    {
        return self::getPathPattern($record['id'],$record['file'],$letterId,$type);
    }

    private static function renameFile(Model $model): bool
    {
        if (!is_null($model->file)){
            $path = self::getRootPath();
            $letterId = $model->{self::$RelatedName}()->get('id')->first();
            if ($letterId != null) $letterId = $letterId->id;
            $type = $model->appendix_other_type ?? null;

            $modeArray = $model->toArray();
            $modeArray['file'] = explode('.', $modeArray['file'])[1];
            $newPath = self::getFilePathByArray($letterId, $modeArray, $type);
            $oldPath = $path . $model->file;

// استخراج پوشه مقصد از مسیر جدید
            $destinationDirectory = dirname($path . $newPath);

// بررسی و ساخت پوشه اگر وجود نداشت
            if (!File::exists($destinationDirectory)) {
                File::makeDirectory($destinationDirectory, 0755, true);
            }

// جابجایی فایل
            if (File::move($oldPath, $path . $newPath)) {
                $model->file = $modeArray['file'];
                return $model->save();
            }
        }
        return false;
    }

    protected static function BootFileUpdateEvent(Model $model)
    {
        if (!is_null($model->getOriginal('file')) && $model->file != $model->getOriginal('file')) {
            $letterId = $model->{self::$RelatedName}()->get('id')->first();
            if ($letterId != null) $letterId = $letterId->id;
            File::delete(
                self::getRootPath() .
                self::getFilePathByArray($letterId,$model->getOriginal(),$model->appendix_other_type ?? null)
            );
            if (str_contains($model->file,'.')) self::renameFile($model);
        }
    }

    protected static function BootFileDeleteEvent(Model $model)
    {
        File::delete(
            self::getRootPath()
            . $model->getFilePath($model->getOriginal('letter_id'))
        );
    }
}
