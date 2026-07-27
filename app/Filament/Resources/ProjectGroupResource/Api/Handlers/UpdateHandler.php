<?php
namespace App\Filament\Resources\ProjectGroupResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ProjectGroupResource;
use App\Filament\Resources\ProjectGroupResource\Api\Requests\UpdateProjectGroupRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = ProjectGroupResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update ProjectGroup
     *
     * @param UpdateProjectGroupRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateProjectGroupRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}