<?php
namespace App\Filament\Resources\MinutesResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\MinutesResource\Api\Requests\CreateMinutesRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = MinutesResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Minutes
     *
     * @param CreateMinutesRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateMinutesRequest $request)
    {
        $model = new (static::getModel());


        $data = $request->validated();


        /*
        |--------------------------------------------------------------------------
        | کاربر جاری به عنوان نویسنده
        |--------------------------------------------------------------------------
        */

        $data['typer_id'] = auth()->id();


        $model->fill($data);


        $model->save();


        /*
        |--------------------------------------------------------------------------
        | امضا کنندگان
        |--------------------------------------------------------------------------
        */

        if ($request->has('organ_ids')) {

            $model->organ()->sync(
                $request->input('organ_ids', [])
            );

        }


        /*
        |--------------------------------------------------------------------------
        | دسته بندی
        |--------------------------------------------------------------------------
        */

        if ($request->has('group_ids')) {

            $model->group()->sync(
                $request->input('group_ids', [])
            );

        }


        /*
        |--------------------------------------------------------------------------
        | فایل
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('upload_file')) {


            $file = $request->file('upload_file');


            $extension = $file->getClientOriginalExtension();


            $path = 'minutes/'.$model->id;


            $file->storeAs(
                $path,
                $model->id.'.'.$extension,
                'private_appendix_other'
            );


            $model->file = $extension;


            $model->saveQuietly();
        }


        return static::sendSuccessResponse(
            $model->fresh(),
            "Successfully Create Resource"
        );
    }
}
