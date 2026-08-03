<?php
namespace App\Filament\Resources\OrganTypeResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\OrganTypeResource;
use App\Filament\Resources\OrganTypeResource\Api\Requests\CreateOrganTypeRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = OrganTypeResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create OrganType
     *
     * @param CreateOrganTypeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateOrganTypeRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}