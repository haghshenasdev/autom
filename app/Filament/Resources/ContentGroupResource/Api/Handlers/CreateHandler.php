<?php
namespace App\Filament\Resources\ContentGroupResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ContentGroupResource;
use App\Filament\Resources\ContentGroupResource\Api\Requests\CreateContentGroupRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = ContentGroupResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create ContentGroup
     *
     * @param CreateContentGroupRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateContentGroupRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}