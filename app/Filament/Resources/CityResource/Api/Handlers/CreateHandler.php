<?php
namespace App\Filament\Resources\CityResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\CityResource;
use App\Filament\Resources\CityResource\Api\Requests\CreateCityRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = CityResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create City
     *
     * @param CreateCityRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateCityRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}