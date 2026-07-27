<?php
namespace App\Filament\Resources\ContentGroupResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ContentGroupResource;
use App\Filament\Resources\ContentGroupResource\Api\Requests\UpdateContentGroupRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = ContentGroupResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update ContentGroup
     *
     * @param UpdateContentGroupRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateContentGroupRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}