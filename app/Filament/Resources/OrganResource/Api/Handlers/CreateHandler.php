<?php
namespace App\Filament\Resources\OrganResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\OrganResource;
use App\Filament\Resources\OrganResource\Api\Requests\CreateOrganRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = OrganResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Organ
     *
     * @param CreateOrganRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateOrganRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}