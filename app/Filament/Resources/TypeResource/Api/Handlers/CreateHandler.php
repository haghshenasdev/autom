<?php
namespace App\Filament\Resources\TypeResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\TypeResource;
use App\Filament\Resources\TypeResource\Api\Requests\CreateTypeRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = TypeResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Type
     *
     * @param CreateTypeRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateTypeRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}