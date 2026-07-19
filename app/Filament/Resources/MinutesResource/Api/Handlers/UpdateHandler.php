<?php
namespace App\Filament\Resources\MinutesResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\MinutesResource;
use App\Filament\Resources\MinutesResource\Api\Requests\UpdateMinutesRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = MinutesResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update Minutes
     *
     * @param UpdateMinutesRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateMinutesRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}