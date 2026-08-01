<?php
namespace App\Filament\Resources\LetterResource\Api\Handlers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\LetterResource;
use App\Filament\Resources\LetterResource\Api\Requests\UpdateLetterRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = LetterResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update Letter
     *
     * @param UpdateLetterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateLetterRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        /*
  |--------------------------------------------------------------------------
  | ذخیره فایل جدید
  |--------------------------------------------------------------------------
  */

        if ($request->hasFile('upload_file')) {


            // حذف فایل قبلی
            if ($model->file) {

                Storage::disk('private')->delete(
                    $model->getFilePath()
                );

            }


            $file = $request->file('upload_file');


            // گرفتن پسوند
            $extension = $file->getClientOriginalExtension();


            /*
              مسیر ذخیره:
              private/{id}/{id}.ext

              مطابق getFilePath فعلی مدل
            */

            $path = $model->id;


            $file->storeAs(
                $path,
                $model->id . '.' . $extension,
                'private'
            );


            // فقط پسوند را ذخیره می‌کنیم
            $model->file = $extension;
        }


        $model->saveQuietly();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}
