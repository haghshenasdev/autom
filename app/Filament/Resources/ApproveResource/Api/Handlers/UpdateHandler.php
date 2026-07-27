<?php
namespace App\Filament\Resources\ApproveResource\Api\Handlers;

use Illuminate\Http\Request;
use Rupadana\ApiService\Http\Handlers;
use App\Filament\Resources\ApproveResource;
use App\Filament\Resources\ApproveResource\Api\Requests\UpdateApproveRequest;

class UpdateHandler extends Handlers {
    public static string | null $uri = '/{id}';
    public static string | null $resource = ApproveResource::class;

    public static function getMethod()
    {
        return Handlers::PUT;
    }

    public static function getModel() {
        return static::$resource::getModel();
    }


    /**
     * Update Approve
     *
     * @param UpdateApproveRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handler(UpdateApproveRequest $request)
    {
        $id = $request->route('id');

        $model = static::getModel()::find($id);

        if (!$model) return static::sendNotFoundResponse();

        $model->fill($request->all());

        $model->save();

        return static::sendSuccessResponse($model, "Successfully Update Resource");
    }
}