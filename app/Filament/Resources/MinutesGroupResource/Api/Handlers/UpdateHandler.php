<?php
namespace App\Filament\Resources\MinutesGroupResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\MinutesGroupResource;
use App\Filament\Resources\MinutesGroupResource\Api\Requests\UpdateMinutesGroupRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = MinutesGroupResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update MinutesGroup
     *
     * @param UpdateMinutesGroupRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateMinutesGroupRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}