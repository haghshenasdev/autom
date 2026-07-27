<?php
namespace App\Filament\Resources\LetterResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\LetterResource;
use App\Filament\Resources\LetterResource\Api\Requests\CreateLetterRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = LetterResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Letter
     *
     * @param CreateLetterRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateLetterRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}