<?php
namespace App\Filament\Resources\CartableResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\CartableResource;
use App\Filament\Resources\CartableResource\Api\Requests\CreateCartableRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = CartableResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Cartable
     *
     * @param CreateCartableRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateCartableRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}