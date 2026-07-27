<?php
namespace App\Filament\Resources\AnswerResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\AnswerResource;
use App\Filament\Resources\AnswerResource\Api\Requests\UpdateAnswerRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = AnswerResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update Answer
     *
     * @param UpdateAnswerRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateAnswerRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}