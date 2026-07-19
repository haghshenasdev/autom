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

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}