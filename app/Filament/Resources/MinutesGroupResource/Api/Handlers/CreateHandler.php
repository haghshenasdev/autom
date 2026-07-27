<?php
namespace App\Filament\Resources\MinutesGroupResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\MinutesGroupResource;
use App\Filament\Resources\MinutesGroupResource\Api\Requests\CreateMinutesGroupRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = MinutesGroupResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create MinutesGroup
     *
     * @param CreateMinutesGroupRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateMinutesGroupRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}