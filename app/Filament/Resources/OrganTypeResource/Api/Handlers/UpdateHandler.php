<?php
namespace App\Filament\Resources\OrganTypeResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\OrganTypeResource;
use App\Filament\Resources\OrganTypeResource\Api\Requests\UpdateOrganTypeRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = OrganTypeResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update OrganType
     *
     * @param UpdateOrganTypeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateOrganTypeRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}