<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TempFileService
{

    private string $folder = 'temp_files';


    private function cleanOldFiles()
    {
        $files = Storage::disk('local')->files($this->folder);


        foreach ($files as $file) {

            $path = storage_path('app/' . $file);


            if (file_exists($path)) {

                // زمان آخرین تغییر فایل
                $modified = filemtime($path);


                // بیشتر از 5 دقیقه
                if (time() - $modified > 300) {

                    Storage::disk('local')->delete($file);

                }
            }
        }
    }



    public function save($content, $extension)
    {

        // پاک کردن فایل های قدیمی
        $this->cleanOldFiles();


        $filename = Str::uuid() . '.' . $extension;


        $path = $this->folder . '/' . $filename;


        Storage::disk('local')->put(
            $path,
            $content
        );


        return $filename;
    }


}
