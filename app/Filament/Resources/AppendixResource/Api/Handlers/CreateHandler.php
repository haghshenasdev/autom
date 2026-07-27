<?php
namespace App\Filament\Resources\AppendixResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\AppendixResource;
use App\Filament\Resources\AppendixResource\Api\Requests\CreateAppendixRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = AppendixResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Appendix
     *
     * @param CreateAppendixRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateAppendixRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}