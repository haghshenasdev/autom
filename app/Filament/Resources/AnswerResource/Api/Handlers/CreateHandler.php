<?php
namespace App\Filament\Resources\AnswerResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\AnswerResource;
use App\Filament\Resources\AnswerResource\Api\Requests\CreateAnswerRequest;

class CreateHandler extends Handlers {
    public static string | null $uri = '/';
    public static string | null $resource = AnswerResource::class;

    public static function getMethod()
    {
        return Handlers::POST;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }

    /**
     * Create Answer
     *
     * @param CreateAnswerRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(CreateAnswerRequest $request)
    {
        $model = new (static::getModel());

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Create Resource");
    }
}